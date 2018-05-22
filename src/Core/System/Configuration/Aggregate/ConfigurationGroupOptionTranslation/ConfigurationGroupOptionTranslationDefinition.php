<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Application\Language\LanguageDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationDetailCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event\ConfigurationGroupOptionTranslationDeletedEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Event\ConfigurationGroupOptionTranslationWrittenEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct\ConfigurationGroupOptionTranslationBasicStruct;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct\ConfigurationGroupOptionTranslationDetailStruct;

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
            (new ReferenceVersionField(ConfigurationGroupOptionDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('configurationGroupOption', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', \Shopware\Application\Language\LanguageDefinition::class, false),
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
