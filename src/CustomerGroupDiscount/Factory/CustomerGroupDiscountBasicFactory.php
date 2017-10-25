<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Extension\CustomerGroupDiscountExtension;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicStruct;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerGroupDiscountBasicFactory extends Factory
{
    const ROOT_NAME = 'customer_group_discount';
    const EXTENSION_NAMESPACE = 'customerGroupDiscount';

    const FIELDS = [
       'uuid' => 'uuid',
       'customerGroupUuid' => 'customer_group_uuid',
       'percentageDiscount' => 'percentage_discount',
       'minimumCartAmount' => 'minimum_cart_amount',
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
        CustomerGroupDiscountBasicStruct $customerGroupDiscount,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerGroupDiscountBasicStruct {
        $customerGroupDiscount->setUuid((string) $data[$selection->getField('uuid')]);
        $customerGroupDiscount->setCustomerGroupUuid((string) $data[$selection->getField('customerGroupUuid')]);
        $customerGroupDiscount->setPercentageDiscount((float) $data[$selection->getField('percentageDiscount')]);
        $customerGroupDiscount->setMinimumCartAmount((float) $data[$selection->getField('minimumCartAmount')]);
        $customerGroupDiscount->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $customerGroupDiscount->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

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
}
