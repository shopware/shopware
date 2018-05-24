<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryAreaTranslation;

use Shopware\Application\Language\LanguageDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\System\Country\Aggregate\CountryArea\CountryAreaDefinition;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationDetailCollection;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Event\CountryAreaTranslationDeletedEvent;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Event\CountryAreaTranslationWrittenEvent;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Struct\CountryAreaTranslationBasicStruct;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Struct\CountryAreaTranslationDetailStruct;

class CountryAreaTranslationDefinition extends EntityDefinition
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
        return 'country_area_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('country_area_id', 'countryAreaId', CountryAreaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CountryAreaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('countryArea', 'country_area_id', CountryAreaDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CountryAreaTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CountryAreaTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CountryAreaTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CountryAreaTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CountryAreaTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CountryAreaTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CountryAreaTranslationDetailCollection::class;
    }
}
