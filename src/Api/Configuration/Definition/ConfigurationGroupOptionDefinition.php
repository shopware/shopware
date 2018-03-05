<?php

namespace Shopware\Api\Configuration\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Configuration\Repository\ConfigurationGroupOptionRepository;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionBasicStruct;
use Shopware\Api\Configuration\Event\ConfigurationGroupOption\ConfigurationGroupOptionWrittenEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupOption\ConfigurationGroupOptionDeletedEvent;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\WriteOnly;

use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionDetailCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionDetailStruct;            
            

class ConfigurationGroupOptionDefinition extends EntityDefinition
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
        return 'configuration_group_option';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('version_id', 'versionId'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('configuration_group_id', 'configurationGroupId', ConfigurationGroupDefinition::class))->setFlags(new Required()),
            (new IdField('configuration_group_version_id', 'configurationGroupVersionId'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new StringField('color', 'color'),
            new IdField('media_id', 'mediaId'),
            new IdField('media_version_id', 'mediaVersionId'),
            new ManyToOneAssociationField('configurationGroup', 'configuration_group_id', ConfigurationGroupDefinition::class, false),
            (new TranslationsAssociationField('translations', ConfigurationGroupOptionTranslationDefinition::class, 'configuration_group_option_id', false, 'id'))->setFlags(new Required(), new CascadeDelete())
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigurationGroupOptionRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigurationGroupOptionBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigurationGroupOptionDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigurationGroupOptionWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigurationGroupOptionBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigurationGroupOptionTranslationDefinition::class;
    }


    public static function getDetailStructClass(): string
    {
        return ConfigurationGroupOptionDetailStruct::class;
    }
    
    public static function getDetailCollectionClass(): string
    {
        return ConfigurationGroupOptionDetailCollection::class;
    }

}