<?php
declare(strict_types=1);

namespace Xycc\Winter\Route;


use Closure;
use Throwable;
use Xycc\Winter\Route\Exceptions\DuplicatedRouteException;
use Xycc\Winter\Route\Exceptions\InvalidRouteException;
use Xycc\Winter\Route\Exceptions\RouteMatchException;


class Node
{
    /**
     * @var Node[]
     */
    private array $children = [];
    private int $mode;

    private static bool $inserted = false;

    const Root = 0;
    const Static = 1;
    const Regex = 2;
    const Param = 3;
    const Catch = 4;

    private bool $isRoute = false;
    private bool $optional = false;

    private ?string $class = null;
    private ?string $method = null;

    private ?Closure $handler = null;

    private ?string $regex = null;

    private ?self $parent = null;
    private string $group = '';

    public function __construct(
        private string $path = ''
    )
    {
    }

    public static function root(): static
    {
        $root = new static('');
        $root->mode = self::Root;
        return $root;
    }

    /**
     * @param string $path
     * @deprecated
     * @codeCoverageIgnore
     * @return bool
     */
    public function hasChild(string $path): bool
    {
        return count(
                array_filter($this->children, $this->matchChildren($path))
            ) > 0;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * 当前节点的模式
     */
    private function parseMode(string $path): int
    {
        if ($path === '') {
            return self::Static;
        }

        if ($path[0] === '*') {
            return self::Catch;
        }
        if (str_starts_with($path, '{')) {
            return str_contains($path, ':') ? self::Regex : self::Param;
        }
        return self::Static;
    }

    public function addChildren(string $path, string $group, ?string $class = null, ?string $classMethod = null,
                                ?Closure $handler = null): static
    {
        $paths = explode('/', $path);
        $node = $this;
        foreach ($paths as $segment) {
            if ($node->mode === self::Catch) {
                throw new InvalidRouteException('Catch节点不能有子节点');
            }
            $node = $node->addChild($segment);
        }

        if (!self::$inserted && $node->isRoute) {
            throw new DuplicatedRouteException($path);
        }
        self::$inserted = false;
        $node->class = $class;
        $node->method = $classMethod;
        $node->handler = $handler;
        $node->isRoute = true;
        $node->group = $group;
        return $node;
    }

    public function match(string $uri): RouteItem
    {
        $segments = explode('/', trim($uri, '/'));
        ['node' => $node, 'params' => $params] = $this->matchSegment($segments, []);
        if ($node === null || ! $node->isRoute) {
            if ($child = $node?->getEmptyPathChild()) {
                return new RouteItem($params, $child);
            }
            throw new RouteMatchException($uri . ' not found');
        }
        return new RouteItem($params, $node);
    }

    protected function getEmptyPathChild(): ?self
    {
        return current(
            array_filter($this->children, fn (self $child) => $child->path === '' && $child->isRoute)
        ) ?: null;
    }

    protected function matchSegment(array $segments, array $params): array
    {
        if (count($segments) === 0) {
            return ['node' => $this, 'params' => $params];
        }

        $segment = array_shift($segments);
        $children = $this->matchChildren($segment);

        if (count($children) === 0) {
            array_pop($params);
            return ['node' => null, 'params' => $params];
        }

        foreach ($children as $child) {
            $param = match ($child->mode) {
                self::Param, self::Regex => [$segment],
                self::Catch => [implode('/', [$segment, ...$segments])],
                default => []
            };

            if ($child->mode === self::Catch) {
                $segments = [];
            }

            $result = $child->matchSegment($segments, [...$params, ...$param]);
            if ($result['node'] !== null) {
                return $result;
            }
        }

        return ['node' => null, 'params' => $params];
    }

    /**
     * 匹配的所有子节点
     * @return static[]
     */
    protected function matchChildren(string $path): array
    {
        $nodes = array_filter($this->children, fn($item) => $item->childMatch($path));
        return array_values($nodes);
    }

    public function childMatch(string $path): bool
    {
        try {
            return match ($this->mode) {
                self::Static => $this->path === $path,
                self::Regex => !!preg_match('~^' . $this->regex . '$~', $path),
                default => true,
            };
        } catch (Throwable) {
            throw new InvalidRouteException(sprintf('wrong regex pattern: [%s]', $this->regex));
        }
    }

    /**
     * 往子节点添加一个新的节点, 如果节点已经存在，直接返回
     * @param string $path
     * @return $this
     */
    public function addChild(string $path): static
    {
        // sort?
        $new = array_values(array_filter($this->children, fn ($item) => $item->path === $path));
        if (count($new) !== 0) {
            self::$inserted = false;
            return $new[0];
        }

        $mode = $this->parseMode($path);
        $new = match ($mode) {
            self::Static, self::Param, self::Catch => new static($path),
            self::Regex => $this->parseRegex($path),
        };

        $new->mode = $mode;
        if ($mode === self::Param && $path[strlen($path) - 2] === '?') {
            $new->optional = true;
        }
        $new->parent = $this;

        $this->children[] = $new;
        usort($this->children, fn ($a, $b) => $a->mode <=> $b->mode);
        $this->children = array_values($this->children);

        self::$inserted = true;
        return $new;
    }

    protected function parseRegex(string $path): static
    {
        if (!preg_match('~{(?<paramName>[a-zA-Z]\w*):(?<regex>.*)}~', $path, $matches)) {
            throw new InvalidRouteException('无效的路由: '.$path);
        }
        $node = new static($path);
        $node->regex = $matches['regex'];
        return $node;
    }

    public function haveParamName(): bool
    {
        return in_array($this->mode, [self::Regex, self::Param, self::Catch]);
    }

    public function getName(): ?string
    {
        return match ($this->mode) {
            self::Root, self::Static => null,
            self::Regex => $this->getRegexName(),
            self::Param => $this->getParamName(),
            self::Catch => $this->getCatchName(),
        };
    }

    protected function getRegexName(): string
    {
        preg_match('~{(?<paramName>[a-zA-Z]\w*):(?<regex>.*)}~', $this->path, $matches);
        return $matches['paramName'];
    }

    protected function getParamName(): string
    {
        return substr($this->path, 1, strlen($this->path) - 2);
    }

    protected function getCatchName(): string
    {
        if ($this->path === '*') {
            return 'catch';
        } else {
            return substr($this->path, 1);
        }
    }

    /**
     * @return int
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return string|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @return string|null
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function getRegex()
    {
        return $this->regex;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @codeCoverageIgnore
     * @return string
     */
    public function getModeName(): string
    {
        return match ($this->mode) {
            self::Root => '根路由',
            self::Static => '静态路由',
            self::Regex => '正则路由',
            self::Param => '参数路由',
            self::Catch => '捕获路由'
        };
    }

    public function getHandler()
    {
        if ($this->handler !== null) {
            return $this->handler;
        }
        return [$this->class, $this->method];
    }
}