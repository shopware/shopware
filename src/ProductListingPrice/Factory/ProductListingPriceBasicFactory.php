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
       'productUuid' => 'product_uuid',
       'customerGroupUuid' => 'customer_group_uuid',
       'price' => 'price',
       'displayFromPrice' => 'display_from_price',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        ProductListingPriceBasicStruct $productListingPrice,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductListingPriceBasicStruct {
        $productListingPrice->setUuid((string) $data[$selection->getField('uuid')]);
        $productListingPrice->setProductUuid((string) $data[$selection->getField('productUuid')]);
        $productListingPrice->setCustomerGroupUuid((string) $data[$selection->getField('customerGroupUuid')]);
        $productListingPrice->setPrice((float) $data[$selection->getField('price')]);
        $productListingPrice->setDisplayFromPrice((bool) $data[$selection->getField('displayFromPrice')]);
        $productListingPrice->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $productListingPrice->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

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
        $this->joinTranslation($selection, $query, $context);

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

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
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
}
