<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ProductType;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductTypeBehavior;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class PhysicalProductType extends AbstractProductType
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getType(): string
    {
        return ProductEntity::TYPE_PHYSICAL;
    }

    public function getBehavior(): ProductTypeBehavior
    {
        return new ProductTypeBehavior(
            exportable: true,
            downloadable: false,
            shippable: true,
        );
    }

    public function getMatchedIds(array $productIds, Context $context): array
    {
        if ($productIds === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(p.id))
             FROM product p
             LEFT JOIN product_download d ON p.id = d.product_id AND p.version_id = d.product_version_id
             WHERE p.id IN (:ids)
               AND p.version_id = :versionId AND p.type IS NULL
               AND d.product_id IS NULL',
            [
                'ids' => Uuid::fromHexToBytesList($productIds),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );
    }
}
