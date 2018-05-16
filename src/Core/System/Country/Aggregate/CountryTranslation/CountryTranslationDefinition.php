<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation;

use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;
use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationDetailCollection;
use Shopware\System\Country\Aggregate\CountryTranslation\Event\CountryTranslationDeletedEvent;
use Shopware\System\Country\Aggregate\CountryTranslation\Event\CountryTranslationWrittenEvent;
use Shopware\System\Country\CountryDefinition;
use Shopware\System\Country\Aggregate\CountryTranslation\CountryTranslationRepository;
use Shopware\System\Country\Aggregate\CountryTranslation\Struct\CountryTranslationBasicStruct;
use Shopware\System\Country\Aggregate\CountryTranslation\Struct\CountryTranslationDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;

class CountryTranslationDefinition extends EntityDefinition
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
        return 'country_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CountryTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CountryTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CountryTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CountryTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CountryTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CountryTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CountryTranslationDetailCollection::class;
    }
}
