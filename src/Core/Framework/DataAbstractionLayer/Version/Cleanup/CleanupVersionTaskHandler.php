<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @package core
 *
 * @internal
 */
final class CleanupVersionTaskHandler extends ScheduledTaskHandler
{
    private Connection $connection;

    private int $days;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        Connection $connection,
        int $days
    ) {
        parent::__construct($repository);
        $this->connection = $connection;
        $this->days = $days;
    }

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        yield CleanupVersionTask::class;
    }

    public function run(): void
    {
        $time = new \DateTime();
        $time->modify(sprintf('-%s day', $this->days));

        do {
            $result = $this->connection->executeStatement(
                'DELETE FROM version WHERE created_at <= :timestamp LIMIT 1000',
                ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
        } while ($result > 0);

        do {
            $result = $this->connection->executeStatement(
                'DELETE FROM version_commit WHERE created_at <= :timestamp LIMIT 1000',
                ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
        } while ($result > 0);
    }
}
