<?php

namespace Shopware\CustomerGroup\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailStruct;
use Shopware\CustomerGroupDiscount\Factory\CustomerGroupDiscountBasicFactory;
use Shopware\Framework\Factory\ExtensionRegistry;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerGroupDetailFactory extends CustomerGroupBasicFactory
{
    /**
     * @var CustomerGroupDiscountBasicFactory
     */
    protected $customerGroupDiscountFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistry $registry,
        CustomerGroupDiscountBasicFactory $customerGroupDiscountFactory
    ) {
        parent::__construct($connection, $registry);
        $this->customerGroupDiscountFactory = $customerGroupDiscountFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        CustomerGroupBasicStruct $customerGroup,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerGroupBasicStruct {
        /** @var CustomerGroupDetailStruct $customerGroup */
        $customerGroup = parent::hydrate($data, $customerGroup, $selection, $context);

        return $customerGroup;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($discounts = $selection->filter('discounts')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer_group_discount',
                $discounts->getRootEscaped(),
                sprintf('%s.uuid = %s.customer_group_uuid', $selection->getRootEscaped(), $discounts->getRootEscaped())
            );

            $this->customerGroupDiscountFactory->joinDependencies($discounts, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['discounts'] = $this->customerGroupDiscountFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
