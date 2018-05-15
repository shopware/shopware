<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Definition;

use Shopware\System\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;
use Shopware\System\Configuration\Collection\ConfigurationGroupOptionTranslationDetailCollection;
use Shopware\System\Configuration\Event\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDeletedEvent;
use Shopware\System\Configuration\Event\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationWrittenEvent;
use Shopware\System\Configuration\Repository\ConfigurationGroupOptionTranslationRepository;
use Shopware\System\Configuration\Struct\ConfigurationGroupOptionTranslationBasicStruct;
use Shopware\System\Configuration\Struct\ConfigurationGroupOptionTranslationDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;

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
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
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
