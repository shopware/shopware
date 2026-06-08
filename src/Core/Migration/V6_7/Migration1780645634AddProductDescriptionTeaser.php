<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1780645634AddProductDescriptionTeaser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780645634;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'product_translation', 'description_teaser')) {
            return;
        }

        $this->addColumn($connection, 'product_translation', 'description_teaser', 'VARCHAR(512)');

        $this->backfill($connection);
    }

    private function backfill(Connection $connection): void
    {
        $builder = new ProductDescriptionTeaserBuilder(
            new HtmlSanitizer(null, false, [], [ProductDescriptionTeaserBuilder::TEASER_FIELD => ['sets' => []]])
        );

        // Backfills all rows in batches of 1000; the LIMIT caps memory per pass, not the total.
        do {
            $rows = $connection->fetchAllAssociative(
                <<<'SQL'
                SELECT product_id, product_version_id, language_id, description
                FROM product_translation
                WHERE description IS NOT NULL AND description_teaser IS NULL
                LIMIT 1000
                SQL
            );

            foreach ($rows as $row) {
                $connection->executeStatement(
                    <<<'SQL'
                    UPDATE product_translation
                    SET description_teaser = :teaser
                    WHERE product_id = :productId
                      AND product_version_id = :versionId
                      AND language_id = :languageId
                    SQL,
                    [
                        'teaser' => $builder->build($row['description']),
                        'productId' => $row['product_id'],
                        'versionId' => $row['product_version_id'],
                        'languageId' => $row['language_id'],
                    ]
                );
            }
        } while (\count($rows) > 0);
    }
}
