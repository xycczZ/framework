<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;


use Xycc\Winter\Contract\Attributes\Bean;

/**
 * @mixin \Composer\Autoload\ClassLoader
 */
#[Bean]
class ClassLoader
{
    private $loader;

    public function __construct(Application $app)
    {
        $this->loader = require $app->getRootPath() . '/vendor/autoload.php';
    }

    public function __call(string $name, array $arguments)
    {
        return $this->loader->{$name}(...$arguments);
    }
}