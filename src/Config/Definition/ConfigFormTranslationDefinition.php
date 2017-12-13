<?php declare(strict_types=1);

namespace Shopware\Config\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Config\Collection\ConfigFormTranslationDetailCollection;
use Shopware\Config\Event\ConfigFormTranslation\ConfigFormTranslationWrittenEvent;
use Shopware\Config\Repository\ConfigFormTranslationRepository;
use Shopware\Config\Struct\ConfigFormTranslationBasicStruct;
use Shopware\Config\Struct\ConfigFormTranslationDetailStruct;
use Shopware\Locale\Definition\LocaleDefinition;

class ConfigFormTranslationDefinition extends EntityDefinition
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
        return 'config_form_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('config_form_uuid', 'configFormUuid', ConfigFormDefinition::class))->setFlags(new Required()),
            (new FkField('locale_uuid', 'localeUuid', LocaleDefinition::class))->setFlags(new Required()),
            new StringField('label', 'label'),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('configForm', 'config_form_uuid', ConfigFormDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_uuid', LocaleDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigFormTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigFormTranslationBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigFormTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigFormTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigFormTranslationDetailCollection::class;
    }
}
