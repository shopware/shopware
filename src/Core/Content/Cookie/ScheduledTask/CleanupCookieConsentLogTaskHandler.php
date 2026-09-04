<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes cookie consent log entries older than the configured retention period
 * and removes banner configuration snapshots that are no longer referenced.
 *
 * Deletion happens in batches so large tables do not hold locks for too long.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandlerTest
 */
#[Package('framework')]
#[AsMessageHandler(handles: CleanupCookieConsentLogTask::class)]
final class CleanupCookieConsentLogTaskHandler extends ScheduledTaskHandler
{
    public const CONFIG_KEY_RETENTION_DAYS = 'core.cookieConsentRetention.days';

    public const DEFAULT_RETENTION_DAYS = 120;

    private const DELETE_BATCH_SIZE = 10000;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        // getInt() cannot tell an unset config from an explicit 0, so read the raw value:
        // an operator setting 0 wants same-day deletion, not the default retention.
        // Read without a sales channel: the deletes below cover the whole table, so the
        // retention is one value per installation.
        $configured = $this->systemConfigService->get(self::CONFIG_KEY_RETENTION_DAYS);
        $retentionDays = \is_numeric($configured) ? (int) $configured : self::DEFAULT_RETENTION_DAYS;

        // A negative value disables the cleanup, the operator keeps the log forever
        if ($retentionDays < 0) {
            return;
        }

        $deleteBefore = $this->clock->now()
            ->sub(new \DateInterval(\sprintf('P%dD', $retentionDays)))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        do {
            // executeStatement() is typed int|string in DBAL 4, the strict comparison
            // below needs an int or the loop would stop after one batch
            $deleted = (int) $this->connection->executeStatement(
                'DELETE FROM `cookie_consent_log` WHERE `created_at` < :before LIMIT ' . self::DELETE_BATCH_SIZE,
                ['before' => $deleteBefore],
            );
        } while ($deleted === self::DELETE_BATCH_SIZE);

        // Snapshots are kept as long as any log entry references them. The created_at guard
        // avoids deleting a snapshot that a concurrent, not yet committed consent references.
        $this->connection->executeStatement(
            'DELETE `version` FROM `cookie_consent_config_version` AS `version`
                LEFT JOIN `cookie_consent_log` AS `log` ON `log`.`server_config_hash` = `version`.`config_hash`
            WHERE `log`.`id` IS NULL AND `version`.`created_at` < :before',
            ['before' => $deleteBefore],
        );
    }
}
