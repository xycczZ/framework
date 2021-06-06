<?php
declare(strict_types=1);

namespace Xycc\Winter\Event;


use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Event\Attributes\Event;
use Xycc\Winter\Event\Attributes\Listener;

class EventBoot extends Bootstrap
{
    public function boot(ContainerContract $container): void
    {
        $dispatcher = $container->get(EventDispatcher::class);
        $events = $container->getClassesByAttr(Event::class);

        foreach ($events as $event) {
            $dispatcher->addEvents($event->getClassName());
        }

        $listeners = $container->getClassesByAttr(Listener::class);

        foreach ($listeners as $listener) {
            $attr = $listener->getClassAttributes(Listener::class)[0];
            $listenedEvents = $attr->newInstance()->events;
            foreach ($listenedEvents as $listenedEvent) {
                $dispatcher->addListeners($listenedEvent, [$listener->getClassName()]);
            }
        }
    }

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__,
        ];
    }
}