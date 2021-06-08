<?php


namespace Xycc\Winter\Core\Servers;

use Closure;
use Exception;
use Monolog\Logger;
use Swoole\Coroutine;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
//use Swoole\Http\Server;
use Swoole\WebSocket\Server;
use Swoole\Process;
use Swoole\Server\Event;
use Swoole\Server\Task;
use Swoole\Server\TaskResult;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Config\ConfigContract;
use Xycc\Winter\Core\CoreBoot;
use Xycc\Winter\Core\Events\OnRequest;
use Xycc\Winter\Event\EventDispatcher;
use Xycc\Winter\Http\ExceptionManager;
use Xycc\Winter\Http\MiddlewareManager;
use Xycc\Winter\Http\Request\Request;
use Xycc\Winter\Http\Response\Response;
use Xycc\Winter\Route\Router;


#[Bean]
class HttpServer
{
    private Server $server;
    private array $settings = [];

    public function __construct(
        private Application $app,
        private ConfigContract $config,
        private Router $router,
        private ExceptionManager $em,
        private EventDispatcher $dispatcher,
    )
    {
    }

    public function getServer()
    {
        return $this->server;
    }

    public function start(array $serverConfig)
    {
        $this->settings = $serverConfig;
        $server = new Server($serverConfig['host'], $serverConfig['port']);
        if (isset($serverConfig['settings']['log_file'])) {
            if (!is_dir(dirname($serverConfig['settings']['log_file']))) {
                mkdir(dirname($serverConfig['settings']['log_file']));
            }
            if (!file_exists($serverConfig['settings']['log_file'])) {
                touch($serverConfig['settings']['log_file']);
            }
        }
        $server->set($serverConfig['settings']);
        $server->on('start', [$this, 'onStart']);
        $server->on('shutdown', [$this, 'onShutdown']);
        $server->on('workerStart', [$this, 'onWorkerStart']);
        $server->on('request', [$this, 'onRequest']);
        $server->on('close', [$this, 'onClose']);
        $server->on('managerStart', [$this, 'onManagerStart']);
        $server->on('task', [$this, 'onTask']);
        $server->on('finish', [$this, 'onFinish']);
        $server->on('message', fn () => true);

        $this->server = $server;
        foreach (CoreBoot::getProcesses() as $process) {
            $userProcess = new Process(fn ($p) => $process['instance']->run($server, $p), $process['redirect'], $process['pipe'], $process['coroutine']);
            $server->addProcess($userProcess);
        }
        $server->start();
    }

    public function stop($config)
    {
        if (isset($config['settings']['pid_file'])) {
            $pid = file_get_contents($config['settings']['pid_file']);
            Process::kill($pid, SIGTERM);
        }
    }

    public function restart($config)
    {
        $this->stop($config);
        $this->start($config);
    }

    public function onStart(Server $server)
    {
        $name = $this->settings['name'] ?? $this->config->get('app.name');
        @swoole_set_process_name(sprintf('%s: master', $name ?: 'winter'));

        echo sprintf('http server start on http://%s:%s' . PHP_EOL, $server->host, $server->port);
    }

    public function onManagerStart(Server $server)
    {
        $name = $this->settings['name'] ?? $this->config->get('app.name');
        @swoole_set_process_name(sprintf('%s: manager', $name ?: 'winter'));
    }

    public function onShutdown(Server $server)
    {
        $this->app->clearProxy();
        $this->app->clearWeaves();
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        $name = $this->settings['name'] ?? $this->config->get('app.name');

        if ($server->taskworker) {
            @swoole_set_process_name(sprintf('%s: task - %d', $name ?: 'winter', $workerId));
            $this->taskStart($server, $workerId);
        } else {
            @swoole_set_process_name(sprintf('%s: worker - %d', $name ?: 'winter', $workerId));
            $this->workerStart($server, $workerId);
        }
    }

    public function onTask(Server $server, Task $task)
    {
        $data = $task->data;
        switch ($data['type']) {
            case 'listener':
                foreach ($data['listeners'] as $listener) {
                    $this->app->execute([$listener, 'handle'], ['event' => $data['event']]);
                    if ($data['event']->isPropagationStopped()) {
                        return;
                    }
                }
                break;
            default:
                $this->app->get(Logger::class)->error('wrong task type');
                break;
        }
    }

    public function onFinish(Server $server, TaskResult $result)
    {

    }

    protected function taskStart(Server $server, int $workerId)
    {
    }

    protected function workerStart(Server $server, int $workerId)
    {
    }

    public function onClose(Server $server, Event $event)
    {
        $this->app->clearSession($event->fd);
    }

    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        if ($request->server['request_uri'] === '/favicon.ico') {
            $response->end();
            return;
        }

        $this->dispatcher->dispatch(new OnRequest($request));

        Coroutine::getContext()['fd'] = $request->fd;

        [$req, $resp] = $this->prepareReqResp($request, $response);

        try {
            $route = $this->router->match($request->server['request_uri'], $request->server['request_method']);

            $handler = $route->getNode()->getHandler();
            $handler = $this->wrapHandler($handler, $route->getNamedParams());
            $closure = $this->injectMiddleware($route->getNode()->getGroup(), $handler);

            $resp = $closure($req);
        } catch (Exception $e) {
            $resp->setStatusCode(500);
            if (method_exists($e, 'render')) {
                $this->app->execute([$e, 'render']);
            }
            $this->em->catchStatus($e);
            $resp = $this->app->get(Response::class);
            if (!$resp->getContent()) {
                $resp->setContent(['trace' => $e->getTrace(), 'msg' => $e->getMessage()]);
            }
            $resp->send();
            return;
        }

        /**@var Response $resp */
        $this->em->catchStatus(null);
        $resp->send();
        $this->app->clearRequest($request->fd);
    }

    /**
     * @return array<Request, Response>
     */
    protected function prepareReqResp(SwooleRequest $request, SwooleResponse $response)
    {
        $req = $this->app->get(Request::class);
        $resp = $this->app->get(Response::class);
        $req->init($request);
        $resp->setSwooleResponse($response);
        return [$req, $resp];
    }

    protected function injectMiddleware(string $group, Closure $handler)
    {
        $middlewareManager = $this->app->get(MiddlewareManager::class);
        $middlewares = $middlewareManager->getMiddlewares($group);
        $disabledMiddlewares = $this->config->get('app.disabled-middlewares', []);
        $middlewares = array_filter($middlewares, fn (array $info) => !in_array($info['class'], $disabledMiddlewares));
        return array_reduce($this->getMiddlewareObject($middlewares), $this->carry(), $handler);
    }

    protected function carry()
    {
        return function (Closure $stack, $middleware) {
            return function (Request $request) use ($stack, $middleware) {
                return $middleware->handle($request, $stack);
            };
        };
    }

    protected function getMiddlewareObject(array $middlewares)
    {
        return array_reverse(array_map(fn ($info) => $this->app->get($info['class']), $middlewares));
    }

    private function wrapHandler($handler, array $namedParams)
    {
        return function () use ($handler, $namedParams) {
            $resp = $this->app->execute($handler, $namedParams);
            if ($resp instanceof Response) {
                return $resp;
            }
            $response = $this->app->get(Response::class);
            return $response->setContent($resp ?: '');
        };
    }
}