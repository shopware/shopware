<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class CleanupCartTaskHandler extends ScheduledTaskHandler
{
    private Connection $connection;

    private int $days;

    public function __construct(
        EntityRepositoryInterface $repository,
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

        $this->connection->executeStatement(
            'DELETE FROM cart WHERE created_at <= :timestamp',
            ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );
    }
}
