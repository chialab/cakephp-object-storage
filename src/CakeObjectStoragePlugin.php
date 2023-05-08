<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\RouteBuilder;
use Chialab\CakeObjectStorage\Command\CleanupIncompleteCommand;
use Chialab\CakeObjectStorage\Events\DependencyInjectionContainerEventHandler;
use Chialab\CakeObjectStorage\Service\StorageServiceProvider;

/**
 * Plugin for Chialab\CakeObjectStorage
 */
class CakeObjectStoragePlugin extends BasePlugin
{
    /**
     * Add routes for the plugin.
     *
     * If your plugin has many routes, and you would like to isolate them into a separate file,
     * you can create `$plugin/config/routes.php` and delete this method.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Chialab/CakeObjectStorage',
            ['path' => '/'],
            function (RouteBuilder $builder) {
                $builder->setExtensions(['json']);
                $builder->get('/files', ['controller' => 'Files', 'action' => 'index']);
                $builder->post('/files', ['controller' => 'Files', 'action' => 'add']);
                $builder->get('/files/{id}', ['controller' => 'Files', 'action' => 'view'])
                    ->setPass(['id']);
                $builder->delete('/files/{id}', ['controller' => 'Files', 'action' => 'delete'])
                    ->setPass(['id']);
                $builder->post('/files/{id}/upload', ['controller' => 'Files', 'action' => 'upload'])
                    ->setPass(['id']);
                $builder->post('/files/{id}/finalize', ['controller' => 'Files', 'action' => 'finalize'])
                    ->setPass(['id']);
                $builder->delete('/files/{id}/abort', ['controller' => 'Files', 'action' => 'abort'])
                    ->setPass(['id']);
            }
        );
        parent::routes($routes);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if ($app instanceof ContainerApplicationInterface) {
            $app->getEventManager()
                ->on(new DependencyInjectionContainerEventHandler($app, 'Model.initialize'));
        }
    }

    /**
     * @inheritDoc
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        return parent::console($commands)
            ->add('file:cleanup', CleanupIncompleteCommand::class);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        parent::services($container);

        $container->addServiceProvider(new StorageServiceProvider());
    }
}
