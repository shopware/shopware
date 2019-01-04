<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation;

use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ConfigurationGroupTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'configuration_group_translation';
    }

    public static function getCollectionClass(): string
    {
        return ConfigurationGroupTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ConfigurationGroupTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return ConfigurationGroupDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
        ]);
    }
}
