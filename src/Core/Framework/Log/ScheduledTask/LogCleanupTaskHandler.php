<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\ScheduledTask;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @package core
 *
 * @internal
 */
#[AsMessageHandler(handles: LogCleanupTask::class)]
final class LogCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private SystemConfigService $systemConfigService,
        private Connection $connection
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $entryLifetimeSeconds = $this->systemConfigService->getInt('core.logging.entryLifetimeSeconds');
        $maxEntries = $this->systemConfigService->getInt('core.logging.entryLimit');

        if ($entryLifetimeSeconds !== -1) {
            $deleteBefore = (new \DateTime(sprintf('- %s seconds', $entryLifetimeSeconds)))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $this->connection->executeStatement(
                'DELETE FROM `log_entry` WHERE `created_at` < :before',
                ['before' => $deleteBefore]
            );
        }

        if ($maxEntries !== -1) {
            $sql = 'DELETE ld FROM `log_entry` ld LEFT JOIN (
                        SELECT id
                        FROM `log_entry`
                        ORDER BY `created_at`
                        DESC LIMIT :maxEntries
                    ) ls ON ld.ID = ls.ID
                    WHERE ls.ID IS NULL;';

            $statement = $this->connection->prepare($sql);
            $statement->bindValue('maxEntries', $maxEntries, \PDO::PARAM_INT);
            $statement->executeStatement();
        }
    }
}
