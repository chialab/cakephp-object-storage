<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test;

use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;

/**
 * Dummy application for tests.
 */
class DummyApplication extends BaseApplication
{
    /**
     * @inheritDoc
     */
    public function bootstrap(): void
    {
        // override and do nothing
    }

    /**
     * @inheritDoc
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue
            ->add(new BodyParserMiddleware())
            ->add(new RoutingMiddleware($this));
    }
}
