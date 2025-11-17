<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ProductType;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductTypeBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class DigitalProductType extends AbstractProductType
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getType(): string
    {
        return ProductEntity::TYPE_DIGITAL;
    }

    public function getBehavior(): ProductTypeBehavior
    {
        return new ProductTypeBehavior(
            exportable: true,
            downloadable: true,
            shippable: false,
        );
    }

    /**
     * @param list<string> $productIds
     *
     * @return list<string>
     */
    public function getMatchedIds(array $productIds, Context $context): array
    {
        if ($productIds === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_id)) FROM product_download WHERE product_id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList($productIds),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );
    }
}
