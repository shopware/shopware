<?php

namespace Shopware\Api\Configuration\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Configuration\Repository\ConfigurationGroupTranslationRepository;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationBasicCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupTranslationBasicStruct;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationWrittenEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationDeletedEvent;
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

use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationDetailCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupTranslationDetailStruct;            
            

class ConfigurationGroupTranslationDefinition extends EntityDefinition
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
        return 'configuration_group_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('configuration_group_id', 'configurationGroupId', ConfigurationGroupDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('language_version_id', 'languageVersionId'))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('version_id', 'versionId'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('configurationGroup', 'configuration_group_id', ConfigurationGroupDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', ShopDefinition::class, false)
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigurationGroupTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigurationGroupTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigurationGroupTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigurationGroupTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigurationGroupTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }


    public static function getDetailStructClass(): string
    {
        return ConfigurationGroupTranslationDetailStruct::class;
    }
    
    public static function getDetailCollectionClass(): string
    {
        return ConfigurationGroupTranslationDetailCollection::class;
    }

}