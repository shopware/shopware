<?php

namespace Shopware\Holiday\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Holiday\Extension\HolidayExtension;
use Shopware\Holiday\Struct\HolidayBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class HolidayBasicFactory extends Factory
{
    const ROOT_NAME = 'holiday';
    const EXTENSION_NAMESPACE = 'holiday';

    const FIELDS = [
       'uuid' => 'uuid',
       'calculation' => 'calculation',
       'event_date' => 'event_date',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'name' => 'translation.name',
    ];

    public function hydrate(
        array $data,
        HolidayBasicStruct $holiday,
        QuerySelection $selection,
        TranslationContext $context
    ): HolidayBasicStruct {
        $holiday->setUuid((string) $data[$selection->getField('uuid')]);
        $holiday->setCalculation((string) $data[$selection->getField('calculation')]);
        $holiday->setEventDate(new \DateTime($data[$selection->getField('event_date')]));
        $holiday->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $holiday->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $holiday->setName((string) $data[$selection->getField('name')]);

        /** @var $extension HolidayExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($holiday, $data, $selection, $context);
        }

        return $holiday;
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
                'holiday_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.holiday_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
