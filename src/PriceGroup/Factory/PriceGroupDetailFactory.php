<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;
use Shopware\PriceGroup\Struct\PriceGroupDetailStruct;
use Shopware\PriceGroupDiscount\Factory\PriceGroupDiscountBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class PriceGroupDetailFactory extends PriceGroupBasicFactory
{
    /**
     * @var PriceGroupDiscountBasicFactory
     */
    protected $priceGroupDiscountFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        PriceGroupDiscountBasicFactory $priceGroupDiscountFactory
    ) {
        parent::__construct($connection, $registry);
        $this->priceGroupDiscountFactory = $priceGroupDiscountFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        PriceGroupBasicStruct $priceGroup,
        QuerySelection $selection,
        TranslationContext $context
    ): PriceGroupBasicStruct {
        /** @var PriceGroupDetailStruct $priceGroup */
        $priceGroup = parent::hydrate($data, $priceGroup, $selection, $context);

        return $priceGroup;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        $this->joinDiscounts($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['discounts'] = $this->priceGroupDiscountFactory->getAllFields();

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

    private function joinDiscounts(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($discounts = $selection->filter('discounts'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'price_group_discount',
            $discounts->getRootEscaped(),
            sprintf('%s.uuid = %s.price_group_uuid', $selection->getRootEscaped(), $discounts->getRootEscaped())
        );

        $this->priceGroupDiscountFactory->joinDependencies($discounts, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }
}
