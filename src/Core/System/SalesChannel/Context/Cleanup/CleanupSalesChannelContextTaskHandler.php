<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
class CleanupSalesChannelContextTaskHandler extends ScheduledTaskHandler
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
        return [CleanupSalesChannelContextTask::class];
    }

    public function run(): void
    {
        $time = new \DateTime();
        $time->modify(sprintf('-%s day', $this->days));

        $this->connection->executeStatement(
            'DELETE FROM sales_channel_api_context WHERE updated_at <= :timestamp',
            ['timestamp' => $time->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );
    }
}
