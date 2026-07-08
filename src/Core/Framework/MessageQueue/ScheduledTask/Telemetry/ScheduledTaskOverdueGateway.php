<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskOverdueGatewayTest
 */
#[Package('framework')]
readonly class ScheduledTaskOverdueGateway
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Counts tasks still `scheduled` whose next execution time is before `$now`.
     */
    public function countOverdue(\DateTimeInterface $now): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scheduled_task WHERE status = :status AND next_execution_time < :now',
            [
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
