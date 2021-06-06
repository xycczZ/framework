<?php


namespace Xycc\Winter\Http;


use Xycc\Winter\Contract\Attributes\Bean;

#[Bean]
class MiddlewareManager
{
    private array $customMiddlewares = [];
    private array $globalMiddlewares = [];

    public function getMiddlewares(string $group): array
    {
        return array_merge($this->getGlobalMiddlewares(), $this->getCustomMiddlewares($group));
    }

    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    public function setGlobalMiddlewares(array $middlewares)
    {
        $this->globalMiddlewares = $middlewares;
    }

    public function getCustomMiddlewares(string $group): array
    {
        return $this->customMiddlewares[$group] ?? [];
    }

    public function setCustomMiddlewares(array $middlewares): void
    {
        $this->customMiddlewares = $middlewares;
    }
}