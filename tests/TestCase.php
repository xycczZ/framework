<?php


namespace Xycc\Winter\Tests;


use Xycc\Winter\Container\Application;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->appendBoots(TestBootstrap::class);
        $this->app->start(__DIR__ . '/../');
    }
}