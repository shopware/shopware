<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Definition\ProductCategoryDefinition;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Definition\ProductMediaDefinition;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Event\ProductMedia\ProductMediaWrittenEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Uuid;

class InheritanceJoinIdUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $ids, ShopContext $context)
    {
        $version = Uuid::fromStringToBytes($context->getVersionId());

        if (empty($ids)) {
            $this->updateAllJoinIds($version);

            return;
        }

        $this->updatePartialJoinIds($ids, $version);
    }

    public function updateByEvent(GenericWrittenEvent $event)
    {
        /** @var ProductWrittenEvent|null $productWritten */
        $productWritten = $event->getEventByDefinition(ProductDefinition::class);

        $categoryWritten = $event->getEventByDefinition(ProductCategoryDefinition::class);

        $ids = $productWritten ? $productWritten->getIds() : [];

        if ($categoryWritten) {
            $ids = array_merge($ids, array_column($categoryWritten->getIds(), 'productId'));
        }

        $ids = array_filter(array_unique($ids));
        if (!empty($ids)) {
            $this->update($ids, $event->getContext());
        }

        /** @var ProductMediaWrittenEvent|null $mediaWritten */
        $mediaWritten = $event->getEventByDefinition(ProductMediaDefinition::class);

        if ($mediaWritten) {
            $this->mediaWritten($mediaWritten->getIds(), $mediaWritten->getContext());
        }
    }

    private function mediaWritten(array $mediaIds, ShopContext $context)
    {
        $version = Uuid::fromStringToBytes($context->getVersionId());

        $bytes = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $mediaIds);

        $this->connection->executeUpdate('
            UPDATE product, product_media
              SET product.media_join_id = product_media.product_id
            WHERE product_media.id IN (:ids)
            AND product.version_id = product_media.version_id
            AND product.version_id = :version
            AND product_media.product_id = product.id',
            ['ids' => $bytes, 'version' => $version],
            ['ids' => Connection::PARAM_STR_ARRAY, 'version' => \PDO::PARAM_STR]
        );
    }

    /**
     * @param $version
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updateAllJoinIds($version): void
    {
        $this->connection->executeUpdate(
            'UPDATE product SET 
                    product.category_join_id      = IFNULL(
                      (
                        SELECT product_category.product_id 
                        FROM product_category 
                        WHERE product_category.product_id = product.id 
                        AND product.version_id = product_category.product_version_id 
                        LIMIT 1
                      ), 
                      product.parent_id
                    ),
                    
                    product.media_join_id         = IFNULL(
                      (
                        SELECT product_media.product_id 
                        FROM product_media 
                        WHERE product_media.product_id = product.id 
                        AND product.version_id = product_media.version_id 
                        LIMIT 1
                      ), 
                      product.parent_id
                    ),
                    
                    product.context_price_join_id = IFNULL(
                      (
                        SELECT product_context_price.product_id 
                        FROM product_context_price 
                        WHERE product_context_price.product_id = product.id 
                        AND product.version_id = product_context_price.version_id 
                        LIMIT 1
                      ), 
                      product.parent_id
                    )
                    
                WHERE product.version_id = :version',
            ['version' => $version]
        );

        $this->connection->executeUpdate(
            'UPDATE product as variant, product as parent
                 SET
                    variant.tax_join_id          = IFNULL(variant.tax_id, parent.tax_id),
                    variant.manufacturer_join_id = IFNULL(variant.product_manufacturer_id, parent.product_manufacturer_id),
                    variant.unit_join_id         = IFNULL(variant.unit_id, parent.unit_id)
                 WHERE variant.parent_id = parent.id
                 AND variant.version_id = parent.version_id
                 AND variant.version_id = :version',
            ['version' => $version]
        );

        $this->connection->executeUpdate(
            'UPDATE product as variant
                 SET
                    variant.tax_join_id = variant.tax_id,
                    variant.manufacturer_join_id = variant.product_manufacturer_id,
                    variant.unit_join_id = variant.unit_id
                 WHERE variant.parent_id IS NULL 
                 AND variant.version_id = :version',
            ['version' => $version]
        );
    }

    /**
     * @param array $ids
     * @param $version
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updatePartialJoinIds(array $ids, $version): void
    {
        $bytes = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $this->connection->executeUpdate(
            'UPDATE product SET 
                product.category_join_id      = IFNULL(
                  (
                    SELECT product_category.product_id 
                    FROM product_category 
                    WHERE product_category.product_id = product.id 
                    AND product.version_id = product_category.product_version_id 
                    LIMIT 1
                   ), 
                   product.parent_id
                ),
                  
                product.media_join_id         = IFNULL(
                  (
                    SELECT product_media.product_id 
                    FROM product_media 
                    WHERE product_media.product_id = product.id 
                    AND product.version_id = product_media.version_id 
                    LIMIT 1
                  ), 
                  product.parent_id
                ),
                
                product.context_price_join_id = IFNULL(
                  (
                    SELECT product_context_price.product_id 
                    FROM product_context_price 
                    WHERE product_context_price.product_id = product.id 
                    AND product.version_id = product_context_price.version_id 
                    LIMIT 1
                  ), 
                  product.parent_id
                )
                WHERE product.version_id = :version
                AND product.id IN (:ids)',
            ['ids' => $bytes, 'version' => $version],
            ['ids' => Connection::PARAM_STR_ARRAY, 'version' => \PDO::PARAM_STR]
        );

        $this->connection->executeUpdate(
            'UPDATE product as variant, product as parent
             SET
                variant.tax_join_id          = IFNULL(variant.tax_id, parent.tax_id),
                variant.manufacturer_join_id = IFNULL(variant.product_manufacturer_id, parent.product_manufacturer_id),
                variant.unit_join_id         = IFNULL(variant.unit_id, parent.unit_id)
             WHERE (variant.parent_id = parent.id)
             AND variant.id IN (:ids)
             AND variant.version_id = parent.version_id
             AND variant.version_id = :version',
            ['ids' => $bytes, 'version' => $version],
            ['ids' => Connection::PARAM_STR_ARRAY, 'version' => \PDO::PARAM_STR]
        );

        $this->connection->executeUpdate(
            'UPDATE product as variant
             SET
                variant.tax_join_id = variant.tax_id,
                variant.manufacturer_join_id = variant.product_manufacturer_id,
                variant.unit_join_id = variant.unit_id
             WHERE variant.parent_id IS NULL
             AND variant.version_id = :version 
             AND variant.id IN (:ids)',
            ['ids' => $bytes, 'version' => $version],
            ['ids' => Connection::PARAM_STR_ARRAY, 'version' => \PDO::PARAM_STR]
        );
    }
}
