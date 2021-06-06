<?php
namespace PHPSTORM_META {
    registerArgumentsSet('event',
        'start', 'shutdown', 'workerStart', 'workerStop', 'workerExit', 'connect', 'receive', 'request',
        'packet', 'close', 'task', 'finish', 'pipeMessage', 'workerError', 'managerStart',
        'managetStop', 'beforeReload', 'afterReload'
    );
    expectedArguments(\Swoole\Http\Server::on(), 0, argumentsSet('event'));
    expectedArguments(\Swoole\Process\Pool::on(), 0, argumentsSet('event'));

    override(\Swoole\Process\Pool::getProcess(0), map([
        '' => \Swoole\Process::class,
    ]));
    override(\Swoole\Coroutine::getContext(0), [
        '' => \Co\Context::class,
    ]);

    override(\Xycc\Winter\Container\Application::get(0), map([
        '' => '@',
    ]));
}

namespace Co {
    /**
     * 并且执行任务，返回任务的结果数组
     */
    function batch(array $tasks, float $timeout = -1): array
    {
    }

    /**
     * 多次执行一个任务，不返回结果
     */
    function parallel(int $max, callable $fn): void
    {
    }

    /**
     * map...
     */
    function map(array $tasks, callable $fn, float $timeout = -1): array
    {
    }

    function deadlock_check()
    {
    }

    ;

    /**
     * 临时打开协程抢占式调度
     */
    function enableScheduler()
    {
    }

    ;

    function disableScheduler()
    {
    }

    ;
}

namespace Swoole\Coroutine {
    function batch(array $tasks, float $timeout = -1): array
    {
    }

    function parallel(int $max, callable $fn): void
    {
    }

    function map(array $tasks, callable $fn, float $timeout = -1): array
    {
    }

    function deadlock_check()
    {
    }

    ;
    function enableScheduler()
    {
    }

    ;
    function disableScheduler()
    {
    }

    ;
}