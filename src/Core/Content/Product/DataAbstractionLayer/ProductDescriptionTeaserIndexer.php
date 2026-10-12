<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\PostUpdateIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Backfills and repairs `description_teaser`: it rebuilds each teaser from the current description and
 * rewrites only the rows whose stored value is missing or out of date, so products that predate the
 * column or were changed outside the DAL get reconciled. Runs through the post-update flow.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDescriptionTeaserIndexer extends PostUpdateIndexer
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

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids) || $ids === []) {
            return;
        }

        // Rebuild each teaser from the current description and rewrite only rows whose stored value is
        // missing or stale. Live writes stay in sync via ProductDescriptionTeaserSubscriber; this also
        // repairs rows changed outside the DAL (raw SQL) or after the html_sanitizer rules changed —
        // cases the subscriber never sees. The `<=>` pre-filter drops rows already trivially equal in
        // the database; the builder check below is still required, since SQL cannot reproduce the HTML
        // stripping.
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT product_id, product_version_id, language_id, description, description_teaser
                FROM product_translation
                WHERE product_id IN (:ids)
                  AND description IS NOT NULL
                  AND NOT (description <=> description_teaser)
            SQL,
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        foreach ($rows as $row) {
            $teaser = $this->teaserBuilder->build($row['description']);

            if ($teaser === $row['description_teaser']) {
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
                    'teaser' => $teaser,
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
