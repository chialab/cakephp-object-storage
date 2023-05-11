<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Form;

use Cake\Form\Schema;
use Cake\I18n\FrozenTime;
use Cake\Validation\Validator;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\FilePart;
use Chialab\ObjectStorage\MultipartUploadInterface;
use Webmozart\Assert\Assert;

/**
 * Upload Form.
 */
class UploadForm extends BaseForm
{
    /**
     * Builds the schema for the modeless form.
     *
     * @param \Cake\Form\Schema $schema From schema
     * @return \Cake\Form\Schema
     * @codeCoverageIgnore
     */
    protected function _buildSchema(Schema $schema): Schema
    {
        return $schema
            ->addField('id', 'string')
            ->addField('part', 'integer')
            ->addField('content', 'object');
    }

    /**
     * Form validation builder.
     *
     * @param \Cake\Validation\Validator $validator The form validator instance.
     * @return \Cake\Validation\Validator
     * @codeCoverageIgnore
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->notEmptyString('id')
            ->requirePresence('id')

            ->nonNegativeInteger('part')
            ->requirePresence('part', fn (array $context): ?bool => $this->Files->get($context['data']['id'])->is_multipart)

            ->requirePresence('content');
    }

    /**
     * Defines what to execute once the Form is processed
     *
     * @param array $data Form data.
     * @return bool
     * @throws \Exception Rethrown transaction error.
     */
    protected function _execute(array $data): bool
    {
        $this->Files->getConnection()->transactional(function () use ($data): void {
            $file = $this->Files->get($data['id'], ['finder' => 'forFinalization']);

            /** @var \Psr\Http\Message\StreamInterface $content */
            $content = $data['content'];
            /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
            $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);
            if ($file->is_multipart) {
                Assert::notNull($file->multipart_token);
                $this->hash = $storage->multipartUpload(
                    new FileObject($file->getStorageKey(), $content),
                    $file->multipart_token,
                    new FilePart((int)$data['part'], $content, null)
                )->wait();

                return;
            }

            $storage->put(new FileObject($file->getStorageKey(), $content))->wait();
            $file->finalized = FrozenTime::now();
            $this->Files->saveOrFail($file, ['atomic' => false]);
        });

        return true;
    }
}
