<?php
declare(strict_types=1);

namespace Xycc\Winter\Core\Commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Core\Servers\Server;


class ServerCommand extends Command
{
    public function __construct(private Application $app, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('server');
        $this->setAliases(['s']);

        $this->addArgument('action', InputArgument::OPTIONAL, 'start|stop|reload|restart', 'start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        if (! in_array($action, ['start','reload','stop','restart'])) {
            $style->error('服务类型只能是: [http|ws|tcp], 动作只能是: [start|reload|stop|restart]');
            return 1;
        }
        $this->start($action);
        return 0;
    }

    private function start(string $action)
    {
        $config = $this->app->get('config');
        $serverConfig = $config->get(sprintf('server'));

        $server = $this->app->get(Server::class);
        $server->{$action}($serverConfig);

        return 0;
    }
}