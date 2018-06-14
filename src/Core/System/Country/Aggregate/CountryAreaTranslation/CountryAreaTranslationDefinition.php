<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryAreaTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaDefinition;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationDetailCollection;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Event\CountryAreaTranslationDeletedEvent;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Event\CountryAreaTranslationWrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Struct\CountryAreaTranslationBasicStruct;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Struct\CountryAreaTranslationDetailStruct;
use Shopware\Core\System\Language\LanguageDefinition;

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
