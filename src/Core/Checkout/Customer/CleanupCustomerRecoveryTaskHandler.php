<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupCustomerRecoveryTask::class)]
#[Package('checkout')]
final class CleanupCustomerRecoveryTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 1000;

    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly Connection $connection,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $threshold = new \DateTime();
        $threshold->modify('-48 hour');

        do {
            $result = $this->connection->executeStatement(
                'DELETE FROM customer_recovery WHERE created_at <= :timestamp LIMIT :limit',
                [
                    'timestamp' => $threshold->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ]
            );
        } while ($result >= self::BATCH_SIZE);
    }
}
