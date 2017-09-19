<?php

namespace Shopware\AreaCountryState\Factory;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AreaCountryStateBasicFactory extends Factory
{
    const ROOT_NAME = 'area_country_state';
    const EXTENSION_NAMESPACE = 'areaCountryState';

    const FIELDS = [
       'uuid' => 'uuid',
       'area_country_uuid' => 'area_country_uuid',
       'short_code' => 'short_code',
       'position' => 'position',
       'active' => 'active',
       'name' => 'translation.name',
    ];

    public function hydrate(
        array $data,
        AreaCountryStateBasicStruct $areaCountryState,
        QuerySelection $selection,
        TranslationContext $context
    ): AreaCountryStateBasicStruct {
        $areaCountryState->setUuid((string) $data[$selection->getField('uuid')]);
        $areaCountryState->setAreaCountryUuid((string) $data[$selection->getField('area_country_uuid')]);
        $areaCountryState->setShortCode((string) $data[$selection->getField('short_code')]);
        $areaCountryState->setPosition((int) $data[$selection->getField('position')]);
        $areaCountryState->setActive((bool) $data[$selection->getField('active')]);
        $areaCountryState->setName((string) $data[$selection->getField('name')]);

        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($areaCountryState, $data, $selection, $context);
        }

        return $areaCountryState;
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
                'area_country_state_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.area_country_state_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
