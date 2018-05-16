<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormTranslation;

use Shopware\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationBasicCollection;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationDetailCollection;
use Shopware\System\Config\ConfigFormDefinition;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Event\ConfigFormTranslationDeletedEvent;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Event\ConfigFormTranslationWrittenEvent;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\ConfigFormTranslationRepository;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Struct\ConfigFormTranslationBasicStruct;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Struct\ConfigFormTranslationDetailStruct;
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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('config_form_id', 'configFormId', ConfigFormDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ConfigFormDefinition::class))->setFlags(new Required()),

            (new FkField('locale_id', 'localeId', \Shopware\System\Locale\LocaleDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(\Shopware\System\Locale\LocaleDefinition::class))->setFlags(new Required()),

            new StringField('label', 'label'),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('configForm', 'config_form_id', ConfigFormDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, false),
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

    public static function getDeletedEventClass(): string
    {
        return ConfigFormTranslationDeletedEvent::class;
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
