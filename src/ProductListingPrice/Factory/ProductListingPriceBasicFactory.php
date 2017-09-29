<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\ProductListingPrice\Extension\ProductListingPriceExtension;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductListingPriceBasicFactory extends Factory
{
    const ROOT_NAME = 'product_listing_price_ro';
    const EXTENSION_NAMESPACE = 'productListingPrice';

    const FIELDS = [
       'uuid' => 'uuid',
       'product_uuid' => 'product_uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'price' => 'price',
       'display_from_price' => 'display_from_price',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
    ];

    public function hydrate(
        array $data,
        ProductListingPriceBasicStruct $productListingPrice,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductListingPriceBasicStruct {
        $productListingPrice->setUuid((string) $data[$selection->getField('uuid')]);
        $productListingPrice->setProductUuid((string) $data[$selection->getField('product_uuid')]);
        $productListingPrice->setCustomerGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $productListingPrice->setPrice((float) $data[$selection->getField('price')]);
        $productListingPrice->setDisplayFromPrice((bool) $data[$selection->getField('display_from_price')]);
        $productListingPrice->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $productListingPrice->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);

        /** @var $extension ProductListingPriceExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productListingPrice, $data, $selection, $context);
        }

        return $productListingPrice;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_listing_price_ro_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.product_listing_price_ro_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }
}
