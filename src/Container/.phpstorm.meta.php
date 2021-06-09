<?php
namespace PHPSTORM_META {
    override(\Xycc\Winter\Container\Application::get(0), map([
        '' => '@',
        'app' => \Xycc\Winter\Container\Application::class,
        'config' => \Xycc\Winter\Contract\Config\ConfigContract::class,
    ]));

    override(\Xycc\Winter\Container\Application::getByType(0), map([
        '' => '@',
    ]));
    override(\Xycc\Winter\Container\Factory\BeanFactory::get(0), map([
        '' => '@',
        'config' => \Xycc\Winter\Contract\Config\ConfigContract::class,
    ]));
}