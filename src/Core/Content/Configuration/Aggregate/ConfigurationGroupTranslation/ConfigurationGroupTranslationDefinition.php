<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation;

use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationBasicCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationDetailCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Event\ConfigurationGroupTranslationDeletedEvent;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Event\ConfigurationGroupTranslationWrittenEvent;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationBasicStruct;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationDetailStruct;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;

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
            (new ReferenceVersionField(ConfigurationGroupDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('configurationGroup', 'configuration_group_id', ConfigurationGroupDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
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
