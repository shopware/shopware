<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupVersionTask::class)]
#[Package('core')]
final class CleanupVersionTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        private readonly Connection $connection,
        private readonly int $days
    ) {
        parent::__construct($repository);
    }

    public function run(): void
    {
        $time = new \DateTime();
        $time->modify(sprintf('-%d day', $this->days));

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
