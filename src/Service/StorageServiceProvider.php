<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Service;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\ServiceProvider;
use Chialab\ObjectStorage\MultipartUploadInterface;

/**
 * Service provider for storage adapter.
 */
class StorageServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    protected $provides = [
        MultipartUploadInterface::class,
    ];

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(MultipartUploadInterface::class, Configure::readOrFail('Storage.className'))
            ->addArguments(Configure::read('Storage.args', []));
    }
}
