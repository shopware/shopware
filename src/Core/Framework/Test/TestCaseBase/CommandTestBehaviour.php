<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

trait CommandTestBehaviour
{
    abstract protected static function getKernel(): KernelInterface;

    protected function runCommand(Command $command, InputInterface $input, OutputInterface $output, ?KernelInterface $kernel = null): void
    {
        if (!$kernel) {
            $kernel = $this->getKernel();
        }

        /** @deprecated tag:v6.6.0 - remove again once https://github.com/symfony/symfony/pull/52434 is merged */
        \Closure::bind(function (): void {
            $this->definition->addOption(new InputOption('--profile', null, InputOption::VALUE_OPTIONAL));
        }, $input, Input::class)();

        $commandEvent = new ConsoleCommandEvent($command, $input, $output);
        $kernel->getContainer()->get('event_dispatcher')->dispatch($commandEvent, ConsoleEvents::COMMAND);

        $command->run($input, $output);
    }
}
