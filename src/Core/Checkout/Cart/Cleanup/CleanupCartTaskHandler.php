<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
class CleanupCartTaskHandler extends ScheduledTaskHandler
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

    public static function getHandledMessages(): iterable
    {
        return [CleanupCartTask::class];
    }

    public function run(): void
    {
        $time = new \DateTime();
        $time->modify(sprintf('-%s day', $this->days));

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
