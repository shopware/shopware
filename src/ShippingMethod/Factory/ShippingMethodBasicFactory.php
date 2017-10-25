<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethod\Extension\ShippingMethodExtension;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class ShippingMethodBasicFactory extends Factory
{
    const ROOT_NAME = 'shipping_method';
    const EXTENSION_NAMESPACE = 'shippingMethod';

    const FIELDS = [
       'id' => 'id',
       'uuid' => 'uuid',
       'type' => 'type',
       'active' => 'active',
       'position' => 'position',
       'calculation' => 'calculation',
       'surchargeCalculation' => 'surcharge_calculation',
       'taxCalculation' => 'tax_calculation',
       'shippingFree' => 'shipping_free',
       'shopUuid' => 'shop_uuid',
       'customerGroupUuid' => 'customer_group_uuid',
       'bindShippingfree' => 'bind_shippingfree',
       'bindTimeFrom' => 'bind_time_from',
       'bindTimeTo' => 'bind_time_to',
       'bindInstock' => 'bind_instock',
       'bindLaststock' => 'bind_laststock',
       'bindWeekdayFrom' => 'bind_weekday_from',
       'bindWeekdayTo' => 'bind_weekday_to',
       'bindWeightFrom' => 'bind_weight_from',
       'bindWeightTo' => 'bind_weight_to',
       'bindPriceFrom' => 'bind_price_from',
       'bindPriceTo' => 'bind_price_to',
       'bindSql' => 'bind_sql',
       'statusLink' => 'status_link',
       'calculationSql' => 'calculation_sql',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'name' => 'translation.name',
       'description' => 'translation.description',
       'comment' => 'translation.comment',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

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
        $shippingMethod->setSurchargeCalculation(isset($data[$selection->getField('surchargeCalculation')]) ? (int) $data[$selection->getField('surchargeCalculation')] : null);
        $shippingMethod->setTaxCalculation((int) $data[$selection->getField('taxCalculation')]);
        $shippingMethod->setShippingFree(isset($data[$selection->getField('shippingFree')]) ? (float) $data[$selection->getField('shippingFree')] : null);
        $shippingMethod->setShopUuid(isset($data[$selection->getField('shopUuid')]) ? (string) $data[$selection->getField('shopUuid')] : null);
        $shippingMethod->setCustomerGroupUuid(isset($data[$selection->getField('customerGroupUuid')]) ? (string) $data[$selection->getField('customerGroupUuid')] : null);
        $shippingMethod->setBindShippingfree((int) $data[$selection->getField('bindShippingfree')]);
        $shippingMethod->setBindTimeFrom(isset($data[$selection->getField('bindTimeFrom')]) ? (int) $data[$selection->getField('bindTimeFrom')] : null);
        $shippingMethod->setBindTimeTo(isset($data[$selection->getField('bindTimeTo')]) ? (int) $data[$selection->getField('bindTimeTo')] : null);
        $shippingMethod->setBindInstock(isset($data[$selection->getField('bindInstock')]) ? (bool) $data[$selection->getField('bindInstock')] : null);
        $shippingMethod->setBindLaststock((bool) $data[$selection->getField('bindLaststock')]);
        $shippingMethod->setBindWeekdayFrom(isset($data[$selection->getField('bindWeekdayFrom')]) ? (int) $data[$selection->getField('bindWeekdayFrom')] : null);
        $shippingMethod->setBindWeekdayTo(isset($data[$selection->getField('bindWeekdayTo')]) ? (int) $data[$selection->getField('bindWeekdayTo')] : null);
        $shippingMethod->setBindWeightFrom(isset($data[$selection->getField('bindWeightFrom')]) ? (float) $data[$selection->getField('bindWeightFrom')] : null);
        $shippingMethod->setBindWeightTo(isset($data[$selection->getField('bindWeightTo')]) ? (float) $data[$selection->getField('bindWeightTo')] : null);
        $shippingMethod->setBindPriceFrom(isset($data[$selection->getField('bindPriceFrom')]) ? (float) $data[$selection->getField('bindPriceFrom')] : null);
        $shippingMethod->setBindPriceTo(isset($data[$selection->getField('bindPriceTo')]) ? (float) $data[$selection->getField('bindPriceTo')] : null);
        $shippingMethod->setBindSql(isset($data[$selection->getField('bindSql')]) ? (string) $data[$selection->getField('bindSql')] : null);
        $shippingMethod->setStatusLink(isset($data[$selection->getField('statusLink')]) ? (string) $data[$selection->getField('statusLink')] : null);
        $shippingMethod->setCalculationSql(isset($data[$selection->getField('calculationSql')]) ? (string) $data[$selection->getField('calculationSql')] : null);
        $shippingMethod->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $shippingMethod->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $shippingMethod->setName((string) $data[$selection->getField('name')]);
        $shippingMethod->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $shippingMethod->setComment(isset($data[$selection->getField('comment')]) ? (string) $data[$selection->getField('comment')] : null);

        /** @var $extension ShippingMethodExtension */
        foreach ($this->getExtensions() as $extension) {
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
}
