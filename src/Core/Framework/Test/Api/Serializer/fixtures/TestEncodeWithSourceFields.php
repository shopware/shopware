<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;

class TestEncodeWithSourceFields
{
    public function getEntity(): ProductEntity
    {
        return (new ProductEntity())
            ->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'id' => Uuid::randomHex(),
                'name' => 'name',
                'description' => 'description',
                'taxId' => Uuid::randomHex(),
                'tax' => (new TaxEntity())->assign([
                    '_uniqueIdentifier' => Uuid::randomHex(),
                    'id' => Uuid::randomHex(),
                    'name' => 'name',
                    'taxRate' => 19,
                ])->internalSetEntityName('tax'),
                'manufacturer' => (new ProductManufacturerEntity())->assign([
                    '_uniqueIdentifier' => Uuid::randomHex(),
                    'id' => Uuid::randomHex(),
                    'name' => 'name',
                ])->internalSetEntityName('product_manufacturer'),
                'prices' => new ProductPriceCollection([
                    (new ProductPriceEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'productId' => Uuid::randomHex(),
                        'quantityStart' => 1,
                        'quantityEnd' => 10,
                        'price' => 100,
                        'ruleId' => Uuid::randomHex(),
                    ])->internalSetEntityName('product_price'),
                    (new ProductPriceEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'productId' => Uuid::randomHex(),
                        'quantityStart' => 11,
                        'quantityEnd' => null,
                        'price' => 100,
                        'ruleId' => Uuid::randomHex(),
                    ])->internalSetEntityName('product_price'),
                ]),
            ])->internalSetEntityName('product');
    }

    public function getCriteria(): Criteria
    {
        $criteria = new Criteria();

        $criteria->setSource([
            'id',
            'name',
            'tax',
            'manufacturer.name',
            'prices.quantityStart',
            'prices.price',
        ]);

        return $criteria;
    }
}
