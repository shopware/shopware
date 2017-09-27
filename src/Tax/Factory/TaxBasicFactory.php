<?php

namespace Shopware\Tax\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Tax\Extension\TaxExtension;
use Shopware\Tax\Struct\TaxBasicStruct;

class TaxBasicFactory extends Factory
{
    const ROOT_NAME = 'tax';
    const EXTENSION_NAMESPACE = 'tax';

    const FIELDS = [
       'id' => 'id',
       'uuid' => 'uuid',
       'tax_rate' => 'tax_rate',
       'name' => 'name',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
    ];

    public function hydrate(
        array $data,
        TaxBasicStruct $tax,
        QuerySelection $selection,
        TranslationContext $context
    ): TaxBasicStruct {
        $tax->setId((int) $data[$selection->getField('id')]);
        $tax->setUuid((string) $data[$selection->getField('uuid')]);
        $tax->setRate((float) $data[$selection->getField('tax_rate')]);
        $tax->setName((string) $data[$selection->getField('name')]);
        $tax->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $tax->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);

        /** @var $extension TaxExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($tax, $data, $selection, $context);
        }

        return $tax;
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
                'tax_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.tax_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
