<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Cli;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CliContextSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::COMMAND => ['onCommandRun'],
        ];
    }

    public function onCommandRun(ConsoleCommandEvent $event): void
    {
        $event->getCommand()->addOption('tenant-id', null, InputOption::VALUE_REQUIRED, 'Run command in given tenant environment', getenv('TENANT_ID'));
    }
}
