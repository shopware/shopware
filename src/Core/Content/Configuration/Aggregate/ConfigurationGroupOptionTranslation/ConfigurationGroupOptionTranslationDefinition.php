<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class ConfigurationGroupOptionTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'configuration_group_option_translation';
    }

    public static function getCollectionClass(): string
    {
        return ConfigurationGroupOptionTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ConfigurationGroupOptionTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return ConfigurationGroupOptionDefinition::class;
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
            new AttributesField(),
        ]);
    }
}
