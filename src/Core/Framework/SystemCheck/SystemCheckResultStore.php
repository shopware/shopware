<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\StoredResult;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * Keeps the last result of every system check, so a check's state can be shown without running it again.
 * One row per check, overwritten by every run in any execution context.
 *
 * @internal
 */
#[Package('framework')]
class SystemCheckResultStore
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

    /**
     * @return array<string, StoredResult> keyed by check name
     */
    public function all(): array
    {
        $stored = [];
        foreach ($this->connection->fetchAllAssociative('SELECT name, status, healthy, message, extra, execution_context, checked_at FROM system_check_result') as $row) {
            $status = self::status((string) $row['status']);
            $context = SystemCheckExecutionContext::tryFrom((string) $row['execution_context']);

            // a row written by another version that this one cannot read is left out
            if ($status === null || $context === null) {
                continue;
            }

            $stored[(string) $row['name']] = new StoredResult(
                new Result(
                    name: (string) $row['name'],
                    status: $status,
                    message: (string) $row['message'],
                    healthy: $row['healthy'] === null ? null : (bool) $row['healthy'],
                    extra: $row['extra'] === null ? [] : (array) json_decode((string) $row['extra'], true, flags: \JSON_THROW_ON_ERROR),
                ),
                $context,
                new \DateTimeImmutable((string) $row['checked_at'], new \DateTimeZone('UTC')),
            );
        }

        return $stored;
    }

    private static function status(string $name): ?Status
    {
        foreach (Status::cases() as $status) {
            if ($status->name === $name) {
                return $status;
            }
        }

        return null;
    }
}
