<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\StoredResult;

/**
 * @internal
 */
#[Package('framework')]
class SystemCheckResultStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<Result> $results
     */
    public function save(array $results): void
    {
        $checkedAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $queue = new MultiInsertQueryQueue($this->connection, useReplace: true);

        try {
            foreach ($results as $result) {
                $queue->addInsert('system_check_result', [
                    'name' => $result->name,
                    'status' => $result->status->name,
                    'healthy' => $result->healthy === null ? null : (int) $result->healthy,
                    'message' => $result->message,
                    'extra' => json_encode($result->extra, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION),
                    'checked_at' => $checkedAt,
                ]);
            }

            $queue->execute();
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<string, StoredResult> keyed by check name
     */
    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT name, status, healthy, message, extra, checked_at FROM system_check_result');

        $results = [];
        foreach ($rows as $row) {
            $status = self::status((string) $row['status']);
            if ($status === null) {
                continue;
            }

            $results[(string) $row['name']] = new StoredResult(
                new Result(
                    name: (string) $row['name'],
                    status: $status,
                    message: (string) $row['message'],
                    healthy: $row['healthy'] === null ? null : (bool) $row['healthy'],
                    extra: (array) json_decode((string) $row['extra'], true, flags: \JSON_THROW_ON_ERROR),
                ),
                new \DateTimeImmutable((string) $row['checked_at'], new \DateTimeZone('UTC')),
            );
        }

        return $results;
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
