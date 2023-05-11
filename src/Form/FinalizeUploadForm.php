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
 * FinalizeUpload Form.
 */
class FinalizeUploadForm extends BaseForm
{
    /**
     * Builds the schema for the modeless form.
     *
     * @param \Cake\Form\Schema $schema From schema
     * @return \Cake\Form\Schema
     */
    protected function _buildSchema(Schema $schema): Schema
    {
        return $schema
            ->addField('id', 'string')
            ->addField('hashes', 'array');
    }

    /**
     * Form validation builder.
     *
     * @param \Cake\Validation\Validator $validator The form validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->notEmptyString('id')
            ->requirePresence('id')

            ->addNestedMany(
                'hashes',
                (new Validator())
                    ->nonNegativeInteger('part')
                    ->requirePresence('part')

                    ->scalar('hash')
                    ->notEmptyString('hash')
                    ->requirePresence('hash')
            )
            ->notEmptyArray('hashes')
            ->requirePresence('hashes');
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

            $parts = array_map(fn (array $hash): FilePart => new FilePart((int)$hash['part'], null, $hash['hash']), $data['hashes']);
            /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
            $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);
            $storage->multipartFinalize(new FileObject($file->getStorageKey(), null), $file->multipart_token, ...$parts);
            $file->finalized = FrozenTime::now();
            $file->multipart_token = null;
            $this->Files->saveOrFail($file, ['atomic' => false]);
        });

        return true;
    }
}
