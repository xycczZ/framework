<?php
declare(strict_types=1);

namespace Xycc\Winter\Event;


use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionClass;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Core\Servers\Server;
use Xycc\Winter\Event\Attributes\Event;

#[Bean]
class EventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    /**
     * key: eventName(string) => value: array(listeners(AbstractListener))
     *
     * @param array $events
     */
    public function __construct(
        private ContainerContract $app,
        #[Lazy] private Server $server,
        private array $events = [],
    )
    {
    }

    public function getEvents()
    {
        return $this->events;
    }

    public function dispatch(object $event)
    {
        ['async' => $async, 'listeners' => $listeners] = $this->getListenersForEvent($event);

        $this->runEvents((array)$listeners, $event, $async);
    }

    private function runEvents(array $listeners, object $event, bool $async)
    {
        if ($async) {
            $this->server->getServer()->task(['type' => 'listener', 'listeners' => $listeners, 'event' => $event]);
            return;
        }

        foreach ($listeners as $listener) {
            $this->app->execute([$listener, 'handle'], ['event' => $event]);
            if (method_exists($event, 'isPropagationStopped') && $event->isPropagationStopped()) {
                return;
            }
        }
    }

    public function getListenersForEvent(object $event): iterable
    {
        return $this->events[get_class($event)];
    }

    public function addEvents(string ...$eventClasses)
    {
        foreach ($eventClasses as $eventClass) {
            $this->events[$eventClass] = [
                'async' => $this->isAsyncEvent($eventClass),
                'listeners' => [],
            ];
        }
    }

    public function addListeners(string $class, array $listeners)
    {
        $this->events[$class] ??= ['async' => $this->isAsyncEvent($class), 'listeners' => []];
        $listeners = array_merge($this->events[$class]['listeners'], $listeners);
        $this->events[$class]['listeners'] = array_unique($listeners);
    }

    private function isAsyncEvent(string $class): bool
    {
        $ref = new ReflectionClass($class);
        $attr = $ref->getAttributes(Event::class)[0];
        return $attr->newInstance()->runSeparateProcess;
    }
}