<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cleanup;

use Doctrine\DBAL\Connection;
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
#[AsMessageHandler(handles: CleanupPaymentTokenTask::class)]
#[Package('checkout')]
final class CleanupPaymentTokenTaskHandler extends ScheduledTaskHandler
{
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
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement('DELETE FROM payment_token WHERE expires < :now', ['now' => $now]);
    }
}
