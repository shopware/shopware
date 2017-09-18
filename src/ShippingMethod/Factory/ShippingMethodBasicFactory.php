<?php

namespace Shopware\ShippingMethod\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethod\Extension\ShippingMethodExtension;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class ShippingMethodBasicFactory extends Factory
{
    const ROOT_NAME = 'shipping_method';

    const FIELDS = [
       'id' => 'id',
       'uuid' => 'uuid',
       'type' => 'type',
       'active' => 'active',
       'position' => 'position',
       'calculation' => 'calculation',
       'surcharge_calculation' => 'surcharge_calculation',
       'tax_calculation' => 'tax_calculation',
       'shipping_free' => 'shipping_free',
       'shop_uuid' => 'shop_uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'bind_shippingfree' => 'bind_shippingfree',
       'bind_time_from' => 'bind_time_from',
       'bind_time_to' => 'bind_time_to',
       'bind_instock' => 'bind_instock',
       'bind_laststock' => 'bind_laststock',
       'bind_weekday_from' => 'bind_weekday_from',
       'bind_weekday_to' => 'bind_weekday_to',
       'bind_weight_from' => 'bind_weight_from',
       'bind_weight_to' => 'bind_weight_to',
       'bind_price_from' => 'bind_price_from',
       'bind_price_to' => 'bind_price_to',
       'bind_sql' => 'bind_sql',
       'status_link' => 'status_link',
       'calculation_sql' => 'calculation_sql',
       'name' => 'translation.name',
       'description' => 'translation.description',
       'comment' => 'translation.comment',
    ];

    /**
     * @var ShippingMethodExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        ShippingMethodBasicStruct $shippingMethod,
        QuerySelection $selection,
        TranslationContext $context
    ): ShippingMethodBasicStruct {
        $shippingMethod->setId((int) $data[$selection->getField('id')]);
        $shippingMethod->setUuid((string) $data[$selection->getField('uuid')]);
        $shippingMethod->setType((int) $data[$selection->getField('type')]);
        $shippingMethod->setActive((bool) $data[$selection->getField('active')]);
        $shippingMethod->setPosition((int) $data[$selection->getField('position')]);
        $shippingMethod->setCalculation((int) $data[$selection->getField('calculation')]);
        $shippingMethod->setSurchargeCalculation(isset($data[$selection->getField('surcharge_calculation')]) ? (int) $data[$selection->getField('surcharge_calculation')] : null);
        $shippingMethod->setTaxCalculation((int) $data[$selection->getField('tax_calculation')]);
        $shippingMethod->setShippingFree(isset($data[$selection->getField('shipping_free')]) ? (float) $data[$selection->getField('shipping_free')] : null);
        $shippingMethod->setShopUuid(isset($data[$selection->getField('shop_uuid')]) ? (string) $data[$selection->getField('shop_uuid')] : null);
        $shippingMethod->setCustomerGroupUuid(isset($data[$selection->getField('customer_group_uuid')]) ? (string) $data[$selection->getField('customer_group_uuid')] : null);
        $shippingMethod->setBindShippingfree((int) $data[$selection->getField('bind_shippingfree')]);
        $shippingMethod->setBindTimeFrom(isset($data[$selection->getField('bind_time_from')]) ? (int) $data[$selection->getField('bind_time_from')] : null);
        $shippingMethod->setBindTimeTo(isset($data[$selection->getField('bind_time_to')]) ? (int) $data[$selection->getField('bind_time_to')] : null);
        $shippingMethod->setBindInstock(isset($data[$selection->getField('bind_instock')]) ? (bool) $data[$selection->getField('bind_instock')] : null);
        $shippingMethod->setBindLaststock((bool) $data[$selection->getField('bind_laststock')]);
        $shippingMethod->setBindWeekdayFrom(isset($data[$selection->getField('bind_weekday_from')]) ? (int) $data[$selection->getField('bind_weekday_from')] : null);
        $shippingMethod->setBindWeekdayTo(isset($data[$selection->getField('bind_weekday_to')]) ? (int) $data[$selection->getField('bind_weekday_to')] : null);
        $shippingMethod->setBindWeightFrom(isset($data[$selection->getField('bind_weight_from')]) ? (float) $data[$selection->getField('bind_weight_from')] : null);
        $shippingMethod->setBindWeightTo(isset($data[$selection->getField('bind_weight_to')]) ? (float) $data[$selection->getField('bind_weight_to')] : null);
        $shippingMethod->setBindPriceFrom(isset($data[$selection->getField('bind_price_from')]) ? (float) $data[$selection->getField('bind_price_from')] : null);
        $shippingMethod->setBindPriceTo(isset($data[$selection->getField('bind_price_to')]) ? (float) $data[$selection->getField('bind_price_to')] : null);
        $shippingMethod->setBindSql(isset($data[$selection->getField('bind_sql')]) ? (string) $data[$selection->getField('bind_sql')] : null);
        $shippingMethod->setStatusLink(isset($data[$selection->getField('status_link')]) ? (string) $data[$selection->getField('status_link')] : null);
        $shippingMethod->setCalculationSql(isset($data[$selection->getField('calculation_sql')]) ? (string) $data[$selection->getField('calculation_sql')] : null);
        $shippingMethod->setName((string) $data[$selection->getField('name')]);
        $shippingMethod->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $shippingMethod->setComment(isset($data[$selection->getField('comment')]) ? (string) $data[$selection->getField('comment')] : null);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($shippingMethod, $data, $selection, $context);
        }

        return $shippingMethod;
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
                'shipping_method_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.shipping_method_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
}
