<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PropertyGroupTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'property_group_translation';
    }

    public static function getCollectionClass(): string
    {
        return PropertyGroupTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PropertyGroupTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return PropertyGroupDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new CustomFields(),
        ]);
    }
}
