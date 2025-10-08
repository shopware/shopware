<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: ProductExportGenerateTask::class)]
#[Package('inventory')]
final class ProductExportGenerateTaskHandler extends ScheduledTaskHandler
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
        private readonly MessageBusInterface $messageBus,
        private readonly int $staleMinSeconds = 300,
        private readonly float $staleIntervalFactor = 2.0
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->fetchSalesChannelIds() as $salesChannelId) {
            $productExports = $this->fetchProductExports($salesChannelId);

            if ($productExports === []) {
                continue;
            }

            $now = new \DateTimeImmutable('now');

            foreach ($productExports as $productExport) {
                if (!$this->shouldBeRun($productExport, $now)) {
                    continue;
                }

                $this->messageBus->dispatch(
                    new ProductExportPartialGeneration($productExport['id'], $salesChannelId)
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function fetchSalesChannelIds(): array
    {
        $salesChannelIds = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT `id`
                FROM `sales_channel`
                WHERE `type_id` = :typeId
                  AND `active` = 1
            SQL,
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)],
            ['typeId' => ParameterType::BINARY]
        );

        /** @var list<string> $salesChannelIds */
        return array_values(array_map(
            static fn (string $id): string => Uuid::fromBytesToHex($id),
            array_filter($salesChannelIds, static fn ($id): bool => \is_string($id))
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchProductExports(string $salesChannelId): array
    {
        $productExports = [];
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    LOWER(HEX(pe.id)) AS id,
                    pe.generated_at,
                    pe.interval,
                    pe.is_running,
                    pe.updated_at,
                    pe.created_at
                FROM product_export pe
                INNER JOIN sales_channel sc
                    ON sc.id = pe.sales_channel_id
                INNER JOIN sales_channel_domain scd
                    ON scd.id = pe.sales_channel_domain_id
                WHERE pe.generate_by_cronjob = 1
                  AND sc.active = 1
                  AND (
                        pe.storefront_sales_channel_id = :salesChannelId
                        OR scd.sales_channel_id = :salesChannelId
                  )
            SQL,
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)],
            ['salesChannelId' => ParameterType::BINARY]
        );

        foreach ($rows as $row) {
            if (!\is_string($row['id'])) {
                continue;
            }

            $productExports[] = $row;
        }

        return $productExports;
    }

    /**
     * @param array<string, mixed> $productExport
     */
    private function shouldBeRun(array &$productExport, \DateTimeImmutable $now): bool
    {
        if ($productExport['is_running']) {
            // If a previous run was aborted unexpectedly, the flag may be stuck.
            // Consider the run stale if the entity hasn't been updated for a
            // reasonable duration based on the configured interval.
            if ($this->isStale($productExport, $now)) {
                // Reset the running flag to allow scheduling to continue
                $this->connection->update(
                    'product_export',
                    ['is_running' => 0],
                    ['id' => Uuid::fromHexToBytes($productExport['id'])],
                    ['id' => ParameterType::BINARY]
                );
                $productExport['is_running'] = 1;
            // Fall through to the time-based checks
            } else {
                return false;
            }
        }

        if ($productExport['generated_at'] === null) {
            return true;
        }

        $generatedAt = new \DateTimeImmutable($productExport['generated_at']);

        return $now->getTimestamp() - $generatedAt->getTimestamp() >= $productExport['interval'];
    }

    /**
     * @param array<string, mixed> $productExport
     */
    private function isStale(array $productExport, \DateTimeImmutable $now): bool
    {
        // Determine the last activity timestamp: updatedAt when available, otherwise createdAt
        $lastActivity = $productExport['updated_at'] ?? $productExport['created_at'];
        if ($lastActivity === null) {
            return false;
        }

        $lastActivity = new \DateTimeImmutable($lastActivity);

        // Threshold: max(configured min seconds, configured factor * interval)
        $interval = max(1, $productExport['interval']);
        $threshold = max($this->staleMinSeconds, (int) \ceil($this->staleIntervalFactor * $interval));

        return ($now->getTimestamp() - $lastActivity->getTimestamp()) >= $threshold;
    }
}
