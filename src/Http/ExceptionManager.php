<?php


namespace Xycc\Winter\Http;

use Exception;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Container\ContainerContract;
use Xycc\Winter\Http\Response\Response;

#[Component, NoProxy]
class ExceptionManager
{
    #[Autowired]
    private Response $response;
    #[Autowired]
    private ContainerContract $container;
    private array $handlers = [];

    public function getHandlers()
    {
        return $this->handlers;
    }

    public function setHandlers(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public function catchStatus(?Exception $e)
    {
        $status = $this->response->getStatusCode();
        $exceptionHandlers = [];
        if ($e !== null) {
            $exceptionHandlers = $this->handlers[$status][$e::class] ?? [];
        }

        $globalHandlers = $this->handlers[$status]['all'] ?? [];
        $handlers = array_merge($globalHandlers, $exceptionHandlers);
        foreach ($handlers as $handler) {
            $this->container->execute($handler, ['exception' => $e]);
        }
    }
}