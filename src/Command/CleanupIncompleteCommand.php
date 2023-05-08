<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Expression\QueryExpression;
use Cake\I18n\FrozenTime;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\MultipartUploadInterface;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Webmozart\Assert\Assert;

/**
 * Command to cleanup non-finalized files.
 *
 * @property \Chialab\CakeObjectStorage\Model\Table\FilesTable $Files
 */
class CleanupIncompleteCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected const BATCH_SIZE = 100;

    /**
     * @inheritDoc
     */
    protected $modelClass = 'Chialab/CakeObjectStorage.Files';

    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Command to clean up files that have been created and not finalized for a while.')
            ->addOption('hours', [
                'short' => 'H',
                'help' => 'Number of hours to retain non-finalized files for.',
                'default' => 12,
            ]);
    }

    /**
     * Delete not-finalized files created more than X hours ago.
     * Each batch of stale files to be deleted is wrapped in a transaction.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console I/O.
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io): void
    {
        try {
            /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
            $storage = $this->getContainer()->get(MultipartUploadInterface::class);
            $time = FrozenTime::now()->subHours((int)$args->getOption('hours'));
            $count = 0;
            while (true) {
                $count += $files = $this->Files->getConnection()
                    ->transactional(function () use ($time, $storage): int {
                        $files = $this->Files->find('forFinalization')
                            ->andWhere(function (QueryExpression $exp) use ($time): QueryExpression {
                                return $exp->lt('created', $time);
                            })
                            ->limit(static::BATCH_SIZE);
                        if (!$files->isEmpty()) {
                            $this->Files->deleteManyOrFail($files, ['atomic' => false]);

                            // Delete file manually, because non-atomic delete does not trigger model events
                            foreach ($files as $file) {
                                /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
                                if ($file->is_multipart) {
                                    Assert::notNull($file->multipart_token);
                                    $storage->multipartAbort(new FileObject($file->getStorageKey(), null), $file->multipart_token);

                                    continue;
                                }

                                if ($file->is_finalized) {
                                    $storage->delete($file->getStorageKey());
                                }
                            }
                        }

                        return $files->count();
                    });
                if ($files === 0) {
                    break;
                }
            }

            $io->out(sprintf('Success cleaning up %s stale file uploads.', $count));
        } catch (\Exception $e) {
            $this->log((string)$e);
            $io->abort('Something went wrong');
        }
    }
}
