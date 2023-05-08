<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddFilesTable extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public $autoId = false;

    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('files')
            ->addColumn('id', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('filename', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('mime_type', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('size', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('multipart_token', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('finalized', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['filename'], [
                'name' => 'file_filename_idx',
            ])
            ->addIndex(['mime_type'], [
                'name' => 'file_mime_type_idx',
            ])
            ->addIndex(['size'], [
                'name' => 'file_size_idx',
            ])
            ->addIndex(['created'], [
                'name' => 'file_created_idx',
            ])
            ->addIndex(['finalized'], [
                'name' => 'file_finalized_idx',
            ])
            ->create();
    }
}
