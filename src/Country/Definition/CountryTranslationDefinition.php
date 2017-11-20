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
use Shopware\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Country\Collection\CountryTranslationDetailCollection;
use Shopware\Country\Event\CountryTranslation\CountryTranslationWrittenEvent;
use Shopware\Country\Repository\CountryTranslationRepository;
use Shopware\Country\Struct\CountryTranslationBasicStruct;
use Shopware\Country\Struct\CountryTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new FkField('country_uuid', 'countryUuid', CountryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('country', 'country_uuid', CountryDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
