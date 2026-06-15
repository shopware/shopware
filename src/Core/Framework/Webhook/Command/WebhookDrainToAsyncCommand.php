<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Tests\Integration\Core\Framework\Webhook\Command\WebhookDrainToAsyncCommandTest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Rollback helper for `WEBHOOKS_REWORK`. When the flag is turned off, leftover webhook
 * deliveries are still sitting in the rework queue. This command puts them back onto the
 * regular `async` queue so they get sent. Refuses to run while the flag is on.
 *
 * Rows are updated in place rather than deleted and recreated, because a `webhook_delivery`
 * row's id is its delivery order and recreating would give it a new one.
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - Removed together with the `WEBHOOKS_REWORK` flag in 6.8.
 *
 * @codeCoverageIgnore
 *
 * @see WebhookDrainToAsyncCommandTest
 */
#[AsCommand(
    name: 'webhook:drain-to-async',
    description: 'Re-publish leftover webhook deliveries to the async transport after disabling WEBHOOKS_REWORK',
    help: <<<'HELP'
        Run once after turning <info>WEBHOOKS_REWORK</info> off to recover webhook
        deliveries that were queued for the rework consumer.

          <info>bin/console webhook:drain-to-async</info>

        Prompts before running. Pass <info>-f</info> / <info>--force</info> for
        non-interactive use (CI, deploy scripts).

        Re-dispatches every <info>queued</info> / <info>pending_retry</info> row in
        <info>webhook_delivery</info>. Rows the new async worker already has an envelope for
        (post-flip traffic) will be sent again — delivery is at-least-once and receivers are
        expected to deduplicate via <info>X-Shopware-Event-Id</info>.
        HELP,
)]
#[Package('framework')]
final readonly class WebhookDrainToAsyncCommand
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private Connection $connection,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Skip the interactive confirmation prompt', shortcut: 'f')]
        bool $force = false,
    ): int {
        if (Feature::isActive('WEBHOOKS_REWORK')) {
            $io->error('WEBHOOKS_REWORK is active. This drain is only for after the flag is off — running it now would race the rework consumer.');

            return Command::FAILURE;
        }

        if (!$force && !$io->confirm('Re-publish leftover webhook_delivery rows on the async queue? This may resend in-flight webhooks.', false)) {
            $io->caution('Aborting due to user input.');

            return Command::SUCCESS;
        }

        $maxId = $this->resolveMaxDeliveryId();
        if ($maxId === 0) {
            $io->success('Drain complete: 0 dispatched, 0 unrecoverable.');

            return Command::SUCCESS;
        }

        $cursor = 0;
        $dispatched = 0;
        $unrecoverable = 0;

        while ($cursor < $maxId) {
            $rows = $this->fetchBatch($cursor, $maxId);
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $cursor = (int) $row['delivery_id'];

                if ($this->dispatchRow($row)) {
                    ++$dispatched;
                } else {
                    ++$unrecoverable;
                }
            }
        }

        $io->success(\sprintf('Drain complete: %d dispatched, %d unrecoverable.', $dispatched, $unrecoverable));

        return Command::SUCCESS;
    }

    /**
     * Snapshot the highest `webhook_delivery.id` once so concurrent inserts during the
     * loop are left for the regular consumer to handle.
     */
    private function resolveMaxDeliveryId(): int
    {
        $value = $this->connection->fetchOne('SELECT MAX(id) FROM webhook_delivery');

        return $value === false || $value === null ? 0 : (int) $value;
    }

    /**
     * Pages through `webhook_delivery.id` with a cursor — we reset rows in place, so without
     * the cursor we'd keep seeing the same rows again. `running` rows are skipped because
     * we cannot tell a live in-flight delivery apart from a stale one left by an earlier
     * crash; recover those by hand.
     *
     * @return list<array{delivery_id: int, webhook_event_log_id: string, serialized_webhook_message: string}>
     */
    private function fetchBatch(int $cursor, int $maxId): array
    {
        /** @var list<array{delivery_id: int, webhook_event_log_id: string, serialized_webhook_message: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.id AS delivery_id, d.webhook_event_log_id, el.serialized_webhook_message
             FROM webhook_delivery d
             JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
             WHERE d.id > :cursor
               AND d.id <= :maxId
               AND d.delivery_status IN (:claimable)
               AND el.delivery_status NOT IN (:terminal)
             ORDER BY d.id ASC
             LIMIT :batch',
            [
                'cursor' => $cursor,
                'maxId' => $maxId,
                'claimable' => [
                    WebhookEventLogDefinition::STATUS_QUEUED,
                    WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                ],
                'terminal' => [
                    WebhookEventLogDefinition::STATUS_SUCCESS,
                    WebhookEventLogDefinition::STATUS_FAILED,
                ],
                'batch' => self::BATCH_SIZE,
            ],
            [
                'cursor' => Types::INTEGER,
                'maxId' => Types::INTEGER,
                'claimable' => ArrayParameterType::STRING,
                'terminal' => ArrayParameterType::STRING,
                'batch' => Types::INTEGER,
            ]
        );

        return $rows;
    }

    /**
     * @param array{delivery_id: int, webhook_event_log_id: string, serialized_webhook_message: string} $row
     */
    private function dispatchRow(array $row): bool
    {
        $eventLogId = $row['webhook_event_log_id'];

        /** @phpstan-ignore shopware.unserializeUsage */
        $message = @unserialize($row['serialized_webhook_message'], ['allowed_classes' => [WebhookEventMessage::class]]);

        if (!$message instanceof WebhookEventMessage) {
            $this->logger->warning('webhook:drain-to-async: failed to unserialize message; marking event log failed', [
                'webhookEventId' => Uuid::fromBytesToHex($eventLogId),
            ]);
            $this->markUnrecoverable($eventLogId);

            return false;
        }

        $this->markRowReadyForAsync($eventLogId);

        // Force the message onto `async`. Without the stamp it would go back to the
        // `webhook` queue, which is the one we're draining away from.
        $this->messageBus->dispatch($message, [new TransportNamesStamp(['async'])]);

        return true;
    }

    private function markRowReadyForAsync(string $eventLogId): void
    {
        $this->connection->transactional(function () use ($eventLogId): void {
            $this->connection->executeStatement(
                'UPDATE webhook_delivery
                 SET delivery_status = :queued, next_retry_at = NULL, last_attempt_at = NULL
                 WHERE webhook_event_log_id = :id',
                [
                    'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'id' => $eventLogId,
                ]
            );

            $this->updateEventLogStatus($eventLogId, WebhookEventLogDefinition::STATUS_QUEUED);
        });
    }

    private function markUnrecoverable(string $eventLogId): void
    {
        $this->connection->transactional(function () use ($eventLogId): void {
            $this->updateEventLogStatus($eventLogId, WebhookEventLogDefinition::STATUS_FAILED);

            $this->connection->executeStatement(
                'DELETE FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => $eventLogId]
            );
        });
    }

    private function updateEventLogStatus(string $eventLogId, string $status): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_event_log
             SET delivery_status = :status
             WHERE id = :id
               AND delivery_status NOT IN (:successStatus, :failedStatus)',
            [
                'status' => $status,
                'id' => $eventLogId,
                'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
            ]
        );
    }
}
