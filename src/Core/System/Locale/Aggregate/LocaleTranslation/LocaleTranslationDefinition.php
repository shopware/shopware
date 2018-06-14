<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationBasicCollection;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationDetailCollection;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Event\LocaleTranslationDeletedEvent;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Event\LocaleTranslationWrittenEvent;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Struct\LocaleTranslationBasicStruct;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Struct\LocaleTranslationDetailStruct;
use Shopware\Core\System\Locale\LocaleDefinition;

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
