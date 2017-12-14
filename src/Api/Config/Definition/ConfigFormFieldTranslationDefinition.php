<?php declare(strict_types=1);

namespace Shopware\Api\Config\Definition;

use Shopware\Api\Config\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\Api\Config\Collection\ConfigFormFieldTranslationDetailCollection;
use Shopware\Api\Config\Event\ConfigFormFieldTranslation\ConfigFormFieldTranslationWrittenEvent;
use Shopware\Api\Config\Repository\ConfigFormFieldTranslationRepository;
use Shopware\Api\Config\Struct\ConfigFormFieldTranslationBasicStruct;
use Shopware\Api\Config\Struct\ConfigFormFieldTranslationDetailStruct;
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
use Shopware\Api\Locale\Definition\LocaleDefinition;

class ConfigFormFieldTranslationDefinition extends EntityDefinition
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
        return 'config_form_field_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('config_form_field_uuid', 'configFormFieldUuid', ConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new FkField('locale_uuid', 'localeUuid', LocaleDefinition::class))->setFlags(new Required()),
            new StringField('label', 'label'),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('configFormField', 'config_form_field_uuid', ConfigFormFieldDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_uuid', LocaleDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigFormFieldTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigFormFieldTranslationBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigFormFieldTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormFieldTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigFormFieldTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigFormFieldTranslationDetailCollection::class;
    }
}
