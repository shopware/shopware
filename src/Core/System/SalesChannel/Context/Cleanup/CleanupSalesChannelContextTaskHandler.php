<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @package sales-channel
 *
 * @internal
 */
#[AsMessageHandler(handles: CleanupSalesChannelContextTask::class)]

final class CleanupSalesChannelContextTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        private Connection $connection,
        private int $days
    ) {
        parent::__construct($repository);
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
