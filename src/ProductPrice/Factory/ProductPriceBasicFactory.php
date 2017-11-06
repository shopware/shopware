<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductPrice\Extension\ProductPriceExtension;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;

class ProductPriceBasicFactory extends Factory
{
    const ROOT_NAME = 'product_price';
    const EXTENSION_NAMESPACE = 'productPrice';

    const FIELDS = [
       'uuid' => 'uuid',
       'customerGroupUuid' => 'customer_group_uuid',
       'quantityStart' => 'quantity_start',
       'quantityEnd' => 'quantity_end',
       'productUuid' => 'product_uuid',
       'price' => 'price',
       'pseudoPrice' => 'pseudo_price',
       'basePrice' => 'base_price',
       'percentage' => 'percentage',
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
        ProductPriceBasicStruct $productPrice,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductPriceBasicStruct {
        $productPrice->setUuid((string) $data[$selection->getField('uuid')]);
        $productPrice->setCustomerGroupUuid((string) $data[$selection->getField('customerGroupUuid')]);
        $productPrice->setQuantityStart((int) $data[$selection->getField('quantityStart')]);
        $productPrice->setQuantityEnd(isset($data[$selection->getField('quantityEnd')]) ? (int) $data[$selection->getField('quantityEnd')] : null);
        $productPrice->setProductUuid((string) $data[$selection->getField('productUuid')]);
        $productPrice->setPrice((float) $data[$selection->getField('price')]);
        $productPrice->setPseudoPrice(isset($data[$selection->getField('pseudoPrice')]) ? (float) $data[$selection->getField('pseudoPrice')] : null);
        $productPrice->setBasePrice(isset($data[$selection->getField('basePrice')]) ? (float) $data[$selection->getField('basePrice')] : null);
        $productPrice->setPercentage(isset($data[$selection->getField('percentage')]) ? (float) $data[$selection->getField('percentage')] : null);
        $productPrice->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $productPrice->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

        /** @var $extension ProductPriceExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productPrice, $data, $selection, $context);
        }

        return $productPrice;
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
            'product_price_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.product_price_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
