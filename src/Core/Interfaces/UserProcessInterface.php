<?php


namespace Xycc\Winter\Core\Interfaces;


use Swoole\Http\Server;
use Swoole\Process;

interface UserProcessInterface
{
    /**
     * run in loop
     */
    public function run(Server $server, Process $process): void;
}