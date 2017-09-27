<?php

namespace Shopware\CustomerGroupDiscount\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Extension\CustomerGroupDiscountExtension;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicStruct;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerGroupDiscountBasicFactory extends Factory
{
    const ROOT_NAME = 'customer_group_discount';
    const EXTENSION_NAMESPACE = 'customerGroupDiscount';

    const FIELDS = [
       'uuid' => 'uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'percentage_discount' => 'percentage_discount',
       'minimum_cart_amount' => 'minimum_cart_amount',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
    ];

    public function hydrate(
        array $data,
        CustomerGroupDiscountBasicStruct $customerGroupDiscount,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerGroupDiscountBasicStruct {
        $customerGroupDiscount->setUuid((string) $data[$selection->getField('uuid')]);
        $customerGroupDiscount->setCustomerGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $customerGroupDiscount->setPercentageDiscount((float) $data[$selection->getField('percentage_discount')]);
        $customerGroupDiscount->setMinimumCartAmount((float) $data[$selection->getField('minimum_cart_amount')]);
        $customerGroupDiscount->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $customerGroupDiscount->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);

        /** @var $extension CustomerGroupDiscountExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($customerGroupDiscount, $data, $selection, $context);
        }

        return $customerGroupDiscount;
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
                'customer_group_discount_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.customer_group_discount_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
