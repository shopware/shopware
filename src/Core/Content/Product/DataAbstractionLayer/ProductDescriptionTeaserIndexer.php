<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Subscriber\ProductDescriptionTeaserSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reconciles `description_teaser` with `description`: a full run recomputes every teaser via
 * {@see ProductDescriptionTeaserBuilder} and only writes rows whose teaser actually differs.
 * Scheduled by Migration1780645634AddProductDescriptionTeaser to backfill existing products
 * asynchronously; also usable for recovery via `dal:refresh:index`. Live writes are handled
 * synchronously by {@see ProductDescriptionTeaserSubscriber}, so this indexer intentionally does
 * nothing on the write path.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDescriptionTeaserIndexer extends EntityIndexer
{
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly Connection $connection,
        private readonly ProductDescriptionTeaserBuilder $teaserBuilder
    ) {
    }

    public function getName(): string
    {
        return 'product.description_teaser.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator('product', $offset);

        $ids = $iterator->fetch();
        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        // Live writes are kept in sync synchronously by ProductDescriptionTeaserSubscriber.
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids) || $ids === []) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT product_id, product_version_id, language_id, description, description_teaser
                FROM product_translation
                WHERE product_id IN (:ids)
            SQL,
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        foreach ($rows as $row) {
            $expected = $this->teaserBuilder->build($row['description']);

            if ($expected === $row['description_teaser']) {
                continue;
            }

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE product_translation
                    SET description_teaser = :teaser
                    WHERE product_id = :productId
                      AND product_version_id = :versionId
                      AND language_id = :languageId
                SQL,
                [
                    'teaser' => $expected,
                    'productId' => $row['product_id'],
                    'versionId' => $row['product_version_id'],
                    'languageId' => $row['language_id'],
                ]
            );
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
