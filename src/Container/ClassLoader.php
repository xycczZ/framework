<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;


use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;

/**
 * @mixin \Composer\Autoload\ClassLoader
 */
#[Component]
#[NoProxy]
class ClassLoader
{
    private \Composer\Autoload\ClassLoader $loader;

    public function __construct(Application $app)
    {
        $this->loader = require $app->getRootPath() . '/vendor/autoload.php';
    }

    public function __call(string $name, array $arguments)
    {
        return $this->loader->{$name}(...$arguments);
    }
}