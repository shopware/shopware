<?php declare(strict_types=1);

namespace Shopware\Country\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Country\Collection\CountryAreaTranslationDetailCollection;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationWrittenEvent;
use Shopware\Country\Repository\CountryAreaTranslationRepository;
use Shopware\Country\Struct\CountryAreaTranslationBasicStruct;
use Shopware\Country\Struct\CountryAreaTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new FkField('country_area_uuid', 'countryAreaUuid', CountryAreaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('countryArea', 'country_area_uuid', CountryAreaDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
