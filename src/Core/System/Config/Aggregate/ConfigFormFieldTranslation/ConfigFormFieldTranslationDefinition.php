<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldTranslation;

use Shopware\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationDetailCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Event\ConfigFormFieldTranslationDeletedEvent;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Event\ConfigFormFieldTranslationWrittenEvent;

use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Struct\ConfigFormFieldTranslationBasicStruct;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Struct\ConfigFormFieldTranslationDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\System\Locale\LocaleDefinition;

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('config_form_field_id', 'configFormFieldId', ConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ConfigFormFieldDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('locale_id', 'localeId', \Shopware\System\Locale\LocaleDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(LocaleDefinition::class))->setFlags(new Required()),

            new StringField('label', 'label'),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('configFormField', 'config_form_field_id', ConfigFormFieldDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', \Shopware\System\Locale\LocaleDefinition::class, false),
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

    public static function getDeletedEventClass(): string
    {
        return ConfigFormFieldTranslationDeletedEvent::class;
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
