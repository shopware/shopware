<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthGatewayTest
 */
#[Package('framework')]
readonly class ScheduledTaskHealthGateway
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * The largest number of seconds a due task has been waiting to be queued. A task is "due" when it is
     * still `scheduled` or `skipped` and its next execution time has passed. Returns `0` when nothing is due.
     */
    public function getMaxLatenessSeconds(\DateTimeInterface $now): int
    {
        $oldest = $this->connection->fetchOne(
            'SELECT MIN(next_execution_time) FROM scheduled_task WHERE status IN (:statuses) AND next_execution_time < :now',
            [
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'statuses' => [
                    ScheduledTaskDefinition::STATUS_SCHEDULED,
                    ScheduledTaskDefinition::STATUS_SKIPPED,
                ],
            ],
            [
                'statuses' => ArrayParameterType::STRING,
            ]
        );

        if ($oldest === null || $oldest === false) {
            return 0;
        }

        $lateness = $now->getTimestamp() - (new \DateTimeImmutable((string) $oldest))->getTimestamp();

        return max(0, $lateness);
    }

    /**
     * Counts tasks in the terminal `failed` state: they threw while `shouldRescheduleOnFailure()` was
     * `false` and will not run again until re-activated.
     */
    public function countFailed(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scheduled_task WHERE status = :status',
            ['status' => ScheduledTaskDefinition::STATUS_FAILED]
        );
    }
}
