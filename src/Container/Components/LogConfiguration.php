<?php


namespace Xycc\Winter\Container\Components;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Configuration;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Config\ConfigContract;

#[Configuration]
class LogConfiguration
{
    #[Autowired]
    private ConfigContract $config;

    #[Bean, Lazy]
    public function defaultLogger(): Logger
    {
        $handler = new StreamHandler($this->config->get('app.runtime') . '/logs/app.log', 1);
        $formatter = new LineFormatter(
            '[%datetime%] %channel%.%level_name%: %message% %context% %extra%' . PHP_EOL,
            'Y-m-d H:i:s',
            false,
            false
        );
        $handler->setFormatter($formatter);
        return new Logger('default', [$handler]);
    }
}