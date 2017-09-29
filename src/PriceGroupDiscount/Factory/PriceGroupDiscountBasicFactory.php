<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\PriceGroupDiscount\Extension\PriceGroupDiscountExtension;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class PriceGroupDiscountBasicFactory extends Factory
{
    const ROOT_NAME = 'price_group_discount';
    const EXTENSION_NAMESPACE = 'priceGroupDiscount';

    const FIELDS = [
       'uuid' => 'uuid',
       'priceGroupUuid' => 'price_group_uuid',
       'customerGroupUuid' => 'customer_group_uuid',
       'percentageDiscount' => 'percentage_discount',
       'productCount' => 'product_count',
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
        PriceGroupDiscountBasicStruct $priceGroupDiscount,
        QuerySelection $selection,
        TranslationContext $context
    ): PriceGroupDiscountBasicStruct {
        $priceGroupDiscount->setUuid((string) $data[$selection->getField('uuid')]);
        $priceGroupDiscount->setPriceGroupUuid((string) $data[$selection->getField('priceGroupUuid')]);
        $priceGroupDiscount->setCustomerGroupUuid((string) $data[$selection->getField('customerGroupUuid')]);
        $priceGroupDiscount->setPercentageDiscount((float) $data[$selection->getField('percentageDiscount')]);
        $priceGroupDiscount->setProductCount((float) $data[$selection->getField('productCount')]);
        $priceGroupDiscount->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $priceGroupDiscount->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

        /** @var $extension PriceGroupDiscountExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($priceGroupDiscount, $data, $selection, $context);
        }

        return $priceGroupDiscount;
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
            'price_group_discount_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.price_group_discount_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
