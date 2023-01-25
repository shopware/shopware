<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Sorting;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductSortingTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'product_sorting_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSortingTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSortingTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.2.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ProductSortingDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required()),
        ]);

        return $collection;
    }
}
