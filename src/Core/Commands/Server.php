<?php
declare(strict_types=1);

namespace Xycc\Winter\Core\Commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xycc\Winter\Container\Application;


class Server extends Command
{
    public function __construct(private Application $app, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('server');
        $this->setAliases(['s']);

        $this->addArgument('server', InputArgument::OPTIONAL, '要启动的服务器[http|tcp|ws]', 'http');
        $this->addArgument('action', InputArgument::OPTIONAL, 'start|stop|reload|restart', 'start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $server = $input->getArgument('server');
        $style = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        if (! in_array($server, ['http','ws','tcp']) || ! in_array($action, ['start','reload','stop','restart'])) {
            $style->error('服务类型只能是: [http|ws|tcp], 动作只能是: [start|reload|stop|restart]');
            return 1;
        }
        $this->start($action, $server);
        return 0;
    }

    private function start(string $action, string $server)
    {
        $config = $this->app->get('config');
        $servers = $config->get(sprintf('server.%s-servers', $server));

        foreach ($servers as $serverConfig) {
            $server = $this->app->get($serverConfig['class']);
            $server->{$action}($serverConfig);
        }
        return 0;
    }
}