<?php
namespace PHPSTORM_META {
    override(\Xycc\Winter\Container\Application::get(0), map([
        '' => '@',
        'app' => \Xycc\Winter\Container\Application::class,
        'config' => \Xycc\Winter\Contracts\ConfigContract::class,
    ]));

    override(\Xycc\Winter\Container\Application::getByType(0), map([
        '' => '@',
    ]));
}