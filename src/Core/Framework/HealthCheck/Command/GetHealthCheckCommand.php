<?php declare(strict_types=1);

namespace Shopware\Core\Framework\HealthCheck\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\HealthCheck\Subscriber\HealthCheckEventSubscriber;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'health-check:get',
    description: 'Get health check for application and services.',
)]
#[Package('core')]
class GetHealthCheckCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    )
    {
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context ??= Context::createDefaultContext();

        $event = new HealthCheckEvent($context);
        $event = $this->eventDispatcher->dispatch($event);

        var_dump($event->getServiceDataList());

        return self::SUCCESS;
    }
}
