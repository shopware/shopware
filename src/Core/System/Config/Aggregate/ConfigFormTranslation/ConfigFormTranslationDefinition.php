<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationDetailCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Event\ConfigFormTranslationDeletedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Event\ConfigFormTranslationWrittenEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Struct\ConfigFormTranslationBasicStruct;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Struct\ConfigFormTranslationDetailStruct;
use Shopware\Core\System\Config\ConfigFormDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;

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

            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(LocaleDefinition::class))->setFlags(new Required()),

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
