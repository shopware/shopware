<?php

namespace Shopware\AreaCountry\Factory;

use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AreaCountryBasicFactory extends Factory
{
    const ROOT_NAME = 'area_country';
    const EXTENSION_NAMESPACE = 'areaCountry';

    const FIELDS = [
       'uuid' => 'uuid',
       'iso' => 'iso',
       'area_uuid' => 'area_uuid',
       'position' => 'position',
       'shipping_free' => 'shipping_free',
       'tax_free' => 'tax_free',
       'taxfree_for_vat_id' => 'taxfree_for_vat_id',
       'taxfree_vatid_checked' => 'taxfree_vatid_checked',
       'active' => 'active',
       'iso3' => 'iso3',
       'display_state_in_registration' => 'display_state_in_registration',
       'force_state_in_registration' => 'force_state_in_registration',
       'name' => 'translation.name',
    ];

    public function hydrate(
        array $data,
        AreaCountryBasicStruct $areaCountry,
        QuerySelection $selection,
        TranslationContext $context
    ): AreaCountryBasicStruct {
        $areaCountry->setUuid((string) $data[$selection->getField('uuid')]);
        $areaCountry->setIso(isset($data[$selection->getField('iso')]) ? (string) $data[$selection->getField('iso')] : null);
        $areaCountry->setAreaUuid((string) $data[$selection->getField('area_uuid')]);
        $areaCountry->setPosition((int) $data[$selection->getField('position')]);
        $areaCountry->setShippingFree((bool) $data[$selection->getField('shipping_free')]);
        $areaCountry->setTaxFree((bool) $data[$selection->getField('tax_free')]);
        $areaCountry->setTaxfreeForVatId((bool) $data[$selection->getField('taxfree_for_vat_id')]);
        $areaCountry->setTaxfreeVatidChecked((bool) $data[$selection->getField('taxfree_vatid_checked')]);
        $areaCountry->setActive((bool) $data[$selection->getField('active')]);
        $areaCountry->setIso3(isset($data[$selection->getField('iso3')]) ? (string) $data[$selection->getField('iso3')] : null);
        $areaCountry->setDisplayStateInRegistration((bool) $data[$selection->getField('display_state_in_registration')]);
        $areaCountry->setForceStateInRegistration((bool) $data[$selection->getField('force_state_in_registration')]);
        $areaCountry->setName((string) $data[$selection->getField('name')]);

        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($areaCountry, $data, $selection, $context);
        }

        return $areaCountry;
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
                'area_country_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.area_country_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
