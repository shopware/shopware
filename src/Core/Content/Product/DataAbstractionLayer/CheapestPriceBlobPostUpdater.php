<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\SynchronousPostUpdateIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer\CheapestPriceBlobPostUpdaterTest
 */
#[Package('framework')]
class CheapestPriceBlobPostUpdater extends SynchronousPostUpdateIndexer
{
    public const NAME = 'product.cheapest_price_blob.post_update';

    public const COMPLETED_CONFIG_KEY = 'core.migration.cheapestPriceBlobReconciled';

    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly Connection $connection,
        private readonly CheapestPriceUpdater $updater,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        if ($offset === null && !TableHelper::columnExists($this->connection, 'product', 'cheapest_price')) {
            return null;
        }

        if ($offset === null) {
            // Keep the repair scheduled if a batch fails after the subscriber dequeued it.
            IndexerQueuer::registerIndexer($this->connection, self::NAME);
            $this->systemConfigService->set(self::COMPLETED_CONFIG_KEY, false);
        }

        $iterator = $this->iteratorFactory->createIterator('product', $offset);
        $ids = $iterator->fetch();

        if ($ids === []) {
            // Destructive migrations must not drop the comparison source before every batch succeeded.
            $this->systemConfigService->set(self::COMPLETED_CONFIG_KEY, true);
            (new IndexerQueuer($this->connection))->finishIndexer([self::NAME]);

            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids) || $ids === []) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id))) AS id, LOWER(HEX(version_id)) AS version_id
             FROM product
             WHERE id IN (:ids) AND NOT (CAST(cheapest_price AS BINARY) <=> cheapest_price_blob)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $parentsByVersion = [];
        foreach ($rows as $row) {
            $parentsByVersion[$row['version_id']][] = $row['id'];
        }

        foreach ($parentsByVersion as $versionId => $parentIds) {
            $this->updater->update($parentIds, $message->getContext()->createWithVersionId($versionId));
        }
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator('product')->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }
}
