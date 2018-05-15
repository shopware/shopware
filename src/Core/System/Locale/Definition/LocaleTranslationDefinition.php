<?php declare(strict_types=1);

namespace Shopware\System\Locale\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\System\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\System\Locale\Collection\LocaleTranslationDetailCollection;
use Shopware\System\Locale\Event\LocaleTranslation\LocaleTranslationDeletedEvent;
use Shopware\System\Locale\Event\LocaleTranslation\LocaleTranslationWrittenEvent;
use Shopware\System\Locale\Repository\LocaleTranslationRepository;
use Shopware\System\Locale\Struct\LocaleTranslationBasicStruct;
use Shopware\System\Locale\Struct\LocaleTranslationDetailStruct;

class LocaleTranslationDefinition extends EntityDefinition
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
        return 'locale_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(LocaleDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new StringField('territory', 'territory'))->setFlags(new Required()),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return LocaleTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return LocaleTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return LocaleTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return LocaleTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return LocaleTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return LocaleTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return LocaleTranslationDetailCollection::class;
    }
}
