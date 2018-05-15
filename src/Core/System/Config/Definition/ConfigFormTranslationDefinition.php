<?php declare(strict_types=1);

namespace Shopware\System\Config\Definition;

use Shopware\System\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\System\Config\Collection\ConfigFormTranslationDetailCollection;
use Shopware\System\Config\Event\ConfigFormTranslation\ConfigFormTranslationDeletedEvent;
use Shopware\System\Config\Event\ConfigFormTranslation\ConfigFormTranslationWrittenEvent;
use Shopware\System\Config\Repository\ConfigFormTranslationRepository;
use Shopware\System\Config\Struct\ConfigFormTranslationBasicStruct;
use Shopware\System\Config\Struct\ConfigFormTranslationDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Locale\Definition\LocaleDefinition;

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
