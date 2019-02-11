<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation;

use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductStreamTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'product_stream_translation';
    }

    public static function getCollectionClass(): string
    {
        return ProductStreamTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductStreamTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return ProductStreamDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name'),
            new LongTextField('description', 'description'),
            new AttributesField(),
        ]);
    }
}
