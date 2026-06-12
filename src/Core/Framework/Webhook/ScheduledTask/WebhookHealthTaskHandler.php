<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: WebhookHealthTask::class)]
#[Package('framework')]
final class WebhookHealthTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EndpointLifecycle $lifecycle,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        // shouldRun() gates the enqueue, but a task message queued while the flag was on can
        // still execute after the flag was turned off. Never mutate health under a disabled flag.
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $this->lifecycle->tick();
    }
}
