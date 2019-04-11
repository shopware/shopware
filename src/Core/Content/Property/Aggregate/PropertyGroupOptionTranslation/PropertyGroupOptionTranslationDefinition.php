<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class PropertyGroupOptionTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'property_group_option_translation';
    }

    public static function getCollectionClass(): string
    {
        return PropertyGroupOptionTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PropertyGroupOptionTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return PropertyGroupOptionDefinition::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        $defaults = parent::getDefaults($existence);
        $defaults['position'] = 1;

        return $defaults;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new IntField('position', 'position'),
            new CustomFields(),
        ]);
    }
}
