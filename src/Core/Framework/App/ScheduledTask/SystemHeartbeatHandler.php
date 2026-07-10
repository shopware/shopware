<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Event\SystemHeartbeatEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: SystemHeartbeatTask::class)]
#[Package('framework')]
final class SystemHeartbeatHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->eventDispatcher->dispatch(new SystemHeartbeatEvent());
    }
}
