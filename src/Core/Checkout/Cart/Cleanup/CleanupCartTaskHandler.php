<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 *  @internal
 */
#[AsMessageHandler(handles: CleanupCartTask::class)]
#[Package('checkout')]
final class CleanupCartTaskHandler extends ScheduledTaskHandler
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
                <<<'SQL'
                DELETE FROM cart
                    WHERE (updated_at IS NULL AND created_at <= :timestamp)
                        OR (updated_at IS NOT NULL AND updated_at <= :timestamp) LIMIT 1000;
            SQL,
                ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
        } while ($result > 0);
    }
}
