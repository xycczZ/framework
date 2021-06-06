<?php
declare(strict_types=1);

namespace Xycc\Winter\Route;


use JetBrains\PhpStorm\ExpectedValues;
use Xycc\Winter\Route\Attributes\Route;

class RouteItem
{
    private string $method;

    public function __construct(private array $params,
                                private Node $node)
    {
    }

    public function getNamedParams()
    {
        if (empty($this->params)) {
            return [];
        }

        $params = array_reverse($this->params, false);
        $result = [];
        $node = $this->node;

        foreach ($params as $param) {
            $node = $this->getHaveParamNode($node);
            $name = $node->getName();
            $result[$name] = $param;
            $node = $node->getParent();
        }

        return $result;
    }

    private function getHaveParamNode(Node $node)
    {
        if ($node->haveParamName()) {
            return $node;
        } else {
            return $this->getHaveParamNode($node->getParent());
        }
    }

    /**
     * @return Node
     */
    public function getNode(): Node
    {
        return $this->node;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod(#[ExpectedValues(flagsFromClass: Route::class)] string $method): static
    {
        $this->method = $method;
        return $this;
    }
}