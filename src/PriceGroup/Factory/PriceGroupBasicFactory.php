<?php

namespace Shopware\PriceGroup\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\PriceGroup\Extension\PriceGroupExtension;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class PriceGroupBasicFactory extends Factory
{
    const ROOT_NAME = 'price_group';
    const EXTENSION_NAMESPACE = 'priceGroup';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'translation.name',
    ];

    public function hydrate(
        array $data,
        PriceGroupBasicStruct $priceGroup,
        QuerySelection $selection,
        TranslationContext $context
    ): PriceGroupBasicStruct {
        $priceGroup->setUuid((string) $data[$selection->getField('uuid')]);
        $priceGroup->setName((string) $data[$selection->getField('name')]);

        /** @var $extension PriceGroupExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($priceGroup, $data, $selection, $context);
        }

        return $priceGroup;
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
                'price_group_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.price_group_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
