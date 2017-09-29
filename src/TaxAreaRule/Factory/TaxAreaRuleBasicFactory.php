<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\TaxAreaRule\Extension\TaxAreaRuleExtension;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicStruct;

class TaxAreaRuleBasicFactory extends Factory
{
    const ROOT_NAME = 'tax_area_rule';
    const EXTENSION_NAMESPACE = 'taxAreaRule';

    const FIELDS = [
       'uuid' => 'uuid',
       'area_uuid' => 'area_uuid',
       'area_country_uuid' => 'area_country_uuid',
       'area_country_state_uuid' => 'area_country_state_uuid',
       'tax_uuid' => 'tax_uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'tax_rate' => 'tax_rate',
       'active' => 'active',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'name' => 'translation.name',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        TaxAreaRuleBasicStruct $taxAreaRule,
        QuerySelection $selection,
        TranslationContext $context
    ): TaxAreaRuleBasicStruct {
        $taxAreaRule->setUuid((string) $data[$selection->getField('uuid')]);
        $taxAreaRule->setAreaUuid(isset($data[$selection->getField('area_uuid')]) ? (string) $data[$selection->getField('area_uuid')] : null);
        $taxAreaRule->setAreaCountryUuid(isset($data[$selection->getField('area_country_uuid')]) ? (string) $data[$selection->getField('area_country_uuid')] : null);
        $taxAreaRule->setAreaCountryStateUuid(isset($data[$selection->getField('area_country_state_uuid')]) ? (string) $data[$selection->getField('area_country_state_uuid')] : null);
        $taxAreaRule->setTaxUuid((string) $data[$selection->getField('tax_uuid')]);
        $taxAreaRule->setCustomerGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $taxAreaRule->setTaxRate((float) $data[$selection->getField('tax_rate')]);
        $taxAreaRule->setActive((bool) $data[$selection->getField('active')]);
        $taxAreaRule->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $taxAreaRule->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $taxAreaRule->setName((string) $data[$selection->getField('name')]);

        /** @var $extension TaxAreaRuleExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($taxAreaRule, $data, $selection, $context);
        }

        return $taxAreaRule;
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
            'tax_area_rule_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.tax_area_rule_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
