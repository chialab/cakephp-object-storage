<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;
use Chialab\CakeObjectStorage\Model\Table\FilesTable;
use Chialab\CakeObjectStorage\Test\DummyApplication;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Command\CleanupIncompleteCommand} class.
 *
 * @coversDefaultClass \Chialab\CakeObjectStorage\Command\CleanupIncompleteCommand
 */
class CleanupIncompleteCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var string[]
     */
    protected $fixtures = ['plugin.Chialab/CakeObjectStorage.Files'];

    /**
     * Files table instance
     */
    protected FilesTable $Files;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->useCommandRunner();

        $this->configApplication(DummyApplication::class, ['/']);
        $app = $this->loadPlugins(['Chialab/CakeObjectStorage' => ['services' => false]]);
        $app->getContainer()->addShared(MultipartUploadInterface::class, InMemoryAdapter::class)
            ->addArguments(['https://static.example.com/']);
        $app->getContainer()->extend(MultipartUploadInterface::class)
            ->setConcrete(fn (): InMemoryAdapter => new class ('https://static.example.com/') extends InMemoryAdapter {
                public function getMultipart(): array
                {
                    return $this->multipart;
                }
            });

        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        $this->Files = $table;
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->Files);

        parent::tearDown();
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Command\CleanupIncompleteCommand::execute()} method.
     *
     * @return void
     * @covers ::execute()
     */
    public function testExecution(): void
    {
        $finalizedIds = $this->Files->find('finalized')
            ->all()
            ->extract('id')
            ->toArray();
        $notFinalizedIds = $this->Files->find('notFinalized')
            ->all()
            ->extract('id')
            ->toArray();

        static::assertNotCount(0, $finalizedIds);
        static::assertNotCount(0, $notFinalizedIds);

        // Run command 12 hours in the future
        FrozenTime::setTestNow(FrozenTime::now()->addHour(12));
        $this->exec('cleanup_incomplete -H 0');
        static::assertOutputContains(sprintf('Success cleaning up %s stale file uploads.', count($notFinalizedIds)));

        $afterFinalizedIds = $this->Files->find('finalized')
            ->all()
            ->extract('id')
            ->toArray();
        $afterNotFinalizedIds = $this->Files->find('notFinalized')
            ->all()
            ->extract('id')
            ->toArray();

        sort($finalizedIds);
        sort($afterFinalizedIds);
        static::assertEquals($finalizedIds, $afterFinalizedIds);
        static::assertCount(0, $afterNotFinalizedIds);
    }
}
