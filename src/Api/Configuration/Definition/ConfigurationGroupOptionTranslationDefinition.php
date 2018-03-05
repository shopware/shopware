<?php

namespace Shopware\Api\Configuration\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Configuration\Repository\ConfigurationGroupOptionTranslationRepository;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionTranslationBasicStruct;
use Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationWrittenEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDeletedEvent;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\WriteOnly;

use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationDetailCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionTranslationDetailStruct;            
            

class ConfigurationGroupOptionTranslationDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'configuration_group_option_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('configuration_group_option_id', 'configurationGroupOptionId', ConfigurationGroupOptionDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('language_version_id', 'languageVersionId'))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('version_id', 'versionId'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('configurationGroupOption', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', ShopDefinition::class, false)
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigurationGroupOptionTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigurationGroupOptionTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigurationGroupOptionTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigurationGroupOptionTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigurationGroupOptionTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }


    public static function getDetailStructClass(): string
    {
        return ConfigurationGroupOptionTranslationDetailStruct::class;
    }
    
    public static function getDetailCollectionClass(): string
    {
        return ConfigurationGroupOptionTranslationDetailCollection::class;
    }

}