<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Model\Table;

use Cake\Database\Driver\Sqlite;
use Cake\Database\Expression\QueryExpression;
use Cake\Event\Event;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;
use Chialab\CakeObjectStorage\Model\Entity\File;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\MultipartUploadInterface;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Webmozart\Assert\Assert;

/**
 * File Model
 *
 * @method \Chialab\CakeObjectStorage\Model\Entity\File newEmptyEntity()
 * @method \Chialab\CakeObjectStorage\Model\Entity\File newEntity(array $data, array $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[] newEntities(array $data, array $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File get($primaryKey, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method true deleteOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class FilesTable extends Table implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     * @codeCoverageIgnore
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('files');
        $this->setDisplayField('filename');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     * @codeCoverageIgnore
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->requirePresence('filename', 'create')

            ->scalar('mime_type')
            ->maxLength('mime_type', 255)
            ->allowEmptyString('mime_type')

            ->nonNegativeInteger('size')
            ->notEmptyString('size')
            ->requirePresence('size', 'create');
    }

    /**
     * Generate id and path before persisting entity.
     *
     * @param \Cake\Event\Event $event Dispatched event.
     * @param \Chialab\CakeObjectStorage\Model\Entity\File $file Entity being saved.
     * @return void
     */
    public function beforeSave(Event $event, File $file): void
    {
        if (!$file->isNew()) {
            return;
        }

        $file->id = Text::uuid();
        if ($file->size >= File::getPreferredChunkSize()) {
            /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
            $storage = $this->getContainer()->get(MultipartUploadInterface::class);
            $file->multipart_token = $storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
        }
    }

    /**
     * Delete file after entity has been deleted from database.
     *
     * @param \Cake\Event\Event $event Dispatched event.
     * @param \Chialab\CakeObjectStorage\Model\Entity\File $file Entity being saved.
     * @return void
     */
    public function afterDeleteCommit(Event $event, File $file): void
    {
        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->getContainer()->get(MultipartUploadInterface::class);

        if ($file->is_multipart) {
            Assert::notNull($file->multipart_token);
            $storage->multipartAbort(new FileObject($file->getStorageKey(), null), $file->multipart_token);

            return;
        }

        if ($file->is_finalized) {
            $storage->delete($file->getStorageKey());
        }
    }

    /**
     * Get file URL.
     *
     * @param \Chialab\CakeObjectStorage\Model\Entity\File $file File entity.
     * @return string|null
     */
    public function getFileUrl(File $file): ?string
    {
        if (!$file->is_finalized) {
            return null;
        }

        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->getContainer()->get(MultipartUploadInterface::class);

        return $storage->url($file->getStorageKey());
    }

    /**
     * Find finalized File entities.
     *
     * @param \Cake\ORM\Query $query Query object.
     * @return \Cake\ORM\Query
     */
    protected function findFinalized(Query $query): Query
    {
        return $query->where(
            fn (QueryExpression $exp): QueryExpression => $exp
                ->isNotNull($this->aliasField('finalized'))
        );
    }

    /**
     * Find non-finalized File entities.
     *
     * @param \Cake\ORM\Query $query Query object.
     * @return \Cake\ORM\Query
     */
    protected function findNotFinalized(Query $query): Query
    {
        return $query->where(
            fn (QueryExpression $exp): QueryExpression => $exp
                ->isNull($this->aliasField('finalized'))
        );
    }

    /**
     * Find Files entities that have a multipart upload token saved.
     *
     * @param \Cake\ORM\Query $query Query object.
     * @return \Cake\ORM\Query
     */
    protected function findMultipart(Query $query): Query
    {
        return $query->where(
            fn (QueryExpression $exp): QueryExpression => $exp
                ->isNotNull($this->aliasField('multipart_token'))
        );
    }

    /**
     * Find non-finalized File entities, and lock rows for finalization.
     *
     * @param \Cake\ORM\Query $query Query object.
     * @return \Cake\ORM\Query
     * @codeCoverageIgnore
     */
    protected function findForFinalization(Query $query): Query
    {
        $query = $query->find('notFinalized');

        if (!($query->getConnection()->getDriver() instanceof Sqlite)) {
            $query = $query->epilog('FOR UPDATE');
        }

        return $query;
    }

    /**
     * Find non-finalized File entities that have a multipart upload token saved.
     *
     * @param \Cake\ORM\Query $query Query object.
     * @return \Cake\ORM\Query
     * @codeCoverageIgnore
     */
    protected function findMultipartForFinalization(Query $query): Query
    {
        return $query
            ->find('multipart')
            ->find('forFinalization');
    }
}
