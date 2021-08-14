<?php

namespace Xycc\Winter\Command;

use Symfony\Component\Console\Command\Command;
use Xycc\Winter\Command\Attributes\AsCommand;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Contract\Bootstrap;
use Xycc\Winter\Contract\Container\ContainerContract;

class CommandBoot extends Bootstrap
{
    private ContainerContract $container;

    public static function scanPath(): array
    {
        return [
            __DIR__ => __NAMESPACE__
        ];
    }

    public function boot(ContainerContract $container): void
    {
        $this->container = $container;

        $commands = $container->getClassesByAttr(AsCommand::class, true);
        $app      = $container->getCommand();

        $app->addCommands(array_map(fn(AbstractBeanDefinition $def) => $this->prepareCommand($def), $commands));
    }

    private function prepareCommand(AbstractBeanDefinition $def): Command
    {
        /**@var $command Command */
        $command = $this->container->get($def->getName());

        /**@var $commandAttr AsCommand */
        $commandAttr = $def->getClassAttributes(AsCommand::class, true, false)[0]->newInstance();

        $command->setName($commandAttr->name);
        $command->setDescription($commandAttr->description ?: '');
        return $command;
    }
}