<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * Keeps the last result of every system check, so a check's state can be shown without running it again.
 * One row per check, overwritten by every run in any execution context.
 *
 * @internal
 */
#[Package('framework')]
class SystemCheckResultStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Storing is a side effect of checking: a failure is logged and never fails the check run.
     *
     * @param array<Result> $results
     */
    public function save(array $results, SystemCheckExecutionContext $context): void
    {
        $checkedAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($results as $result) {
            try {
                $this->connection->executeStatement(
                    'INSERT INTO system_check_result (name, status, healthy, message, extra, execution_context, checked_at)
                     VALUES (:name, :status, :healthy, :message, :extra, :context, :checkedAt)
                     ON DUPLICATE KEY UPDATE status = VALUES(status), healthy = VALUES(healthy), message = VALUES(message),
                         extra = VALUES(extra), execution_context = VALUES(execution_context), checked_at = VALUES(checked_at)',
                    [
                        'name' => $result->name,
                        'status' => $result->status->name,
                        'healthy' => $result->healthy === null ? null : (int) $result->healthy,
                        'message' => $result->message,
                        'extra' => json_encode($result->extra, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION),
                        'context' => $context->value,
                        'checkedAt' => $checkedAt,
                    ]
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Could not store the system check result', ['check' => $result->name, 'exception' => $e]);
            }
        }
    }
}
