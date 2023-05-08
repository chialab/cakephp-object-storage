<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Events;

use Cake\Core\Configure;
use Cake\Core\ContainerApplicationInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use League\Container\ContainerAwareInterface;

/**
 * Event handler that takes care of injecting application container taken from an {@see \Cake\Core\ContainerApplicationInterface}
 * to the subjects of the configured events when they implement {@see \League\Container\ContainerAwareInterface}.
 *
 * @example To inject dependency injection container to view instances that implement {@see ContainerAwareInterface}:
 * ```php
 * EventManager::getInstance()->on(new DependencyInjectionContainerEventHandler($application, 'View.beforeRender'));
 * ```
 */
class DependencyInjectionContainerEventHandler implements EventListenerInterface
{
    /**
     * List of implemented events.
     *
     * @var string[]
     */
    protected readonly array $implementedEvents;

    /**
     * Event handler constructor.
     *
     * @param \Cake\Core\ContainerApplicationInterface $application Container application interface.
     * @param string ...$implementedEvents Implemented events.
     */
    public function __construct(protected readonly ContainerApplicationInterface $application, string ...$implementedEvents)
    {
        $this->implementedEvents = $implementedEvents;
    }

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return array_fill_keys($this->implementedEvents, ['callable' => 'inject', 'priority' => 1]);
    }

    /**
     * Inject dependency application container into event subject, if it's an instance of {@see \League\Container\ContainerAwareInterface}.
     *
     * @param \Cake\Event\EventInterface $event Dispatched event.
     * @return void
     */
    public function inject(EventInterface $event): void
    {
        $subject = $event->getSubject();
        if ($subject instanceof ContainerAwareInterface) {
            if (Configure::read('debug', true)) {
                Log::debug(sprintf('Injecting dependency injection container into `%s` during `%s` event', $subject::class, $event->getName()));
            }
            $subject->setContainer($this->application->getContainer());
        }
    }
}
