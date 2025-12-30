<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookOutboxSignalMessage;
use Shopware\Core\Framework\Webhook\Service\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Service\WebhookOutboxProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Scheduled task handler that triggers webhook outbox processing.
 *
 * This handler dispatches a signal message to start the outbox drain process.
 * It also dynamically adjusts the next execution time based on pending retries
 * ensuring that webhook retries happen at the correct time.
 *
 * @internal
 */
#[AsMessageHandler(handles: DrainWebhookOutboxTask::class)]
#[Package('framework')]
final class DrainWebhookOutboxTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
        private readonly OutboxEventRepository $outboxEventRepository,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        if (! $this->outboxEventRepository->hasPendingWork()) {
             return;
        }

        $this->bus->dispatch(new WebhookOutboxSignalMessage());
    }

    /**
     * Override to dynamically adjust next execution time based on pending webhook retries.
     *
     * If there are webhooks waiting for retry with a specific next_retry_at time,
     * we schedule this task to run at that time (if it's sooner than the default interval).
     */
    protected function rescheduleTask(ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        $now = new \DateTimeImmutable();

        // Calculate the default next execution time based on interval
        $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $defaultNextExecution = (new \DateTimeImmutable($nextExecutionTimeString))
            ->modify(\sprintf('+%d seconds', $taskEntity->getRunInterval()));

        if ($defaultNextExecution < $now) {
            $defaultNextExecution = $now;
        }

        // Check if there's a pending retry that needs to happen sooner
        $earliestRetry = $this->outboxEventRepository->getEarliestRetryTime();
        $newNextExecutionTime = $defaultNextExecution;

        if ($earliestRetry !== null) {
            // Use the earlier of the two times
            // Note: getEarliestRetryTime only returns future dates (> NOW)
            if ($earliestRetry < $defaultNextExecution) {
                $newNextExecutionTime = $earliestRetry;
            }
        }

        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'lastExecutionTime' => $now,
                'nextExecutionTime' => $newNextExecutionTime,
            ],
        ], Context::createCLIContext());
    }
}
