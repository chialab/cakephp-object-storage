<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Form;

use Cake\Form\Schema;
use Cake\Validation\Validator;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\MultipartUploadInterface;
use Webmozart\Assert\Assert;

/**
 * Abort Form.
 */
class AbortForm extends BaseForm
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
            ->addField('id', 'string');
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
            ->requirePresence('id');
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
            $file = $this->Files->get($data['id'], ['finder' => 'multipartForFinalization']);
            Assert::notNull($file->multipart_token);

            /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
            $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);
            $storage->multipartAbort(new FileObject($file->getStorageKey(), null), $file->multipart_token);
        });

        return true;
    }
}
