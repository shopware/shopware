<?php declare(strict_types=1);

namespace Shopware\Api\Country\Definition;

use Shopware\Api\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Api\Country\Collection\CountryTranslationDetailCollection;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationDeletedEvent;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationWrittenEvent;
use Shopware\Api\Country\Repository\CountryTranslationRepository;
use Shopware\Api\Country\Struct\CountryTranslationBasicStruct;
use Shopware\Api\Country\Struct\CountryTranslationDetailStruct;
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
use Shopware\Api\Shop\Definition\ShopDefinition;

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
            new VersionField(),
            (new FkField('language_id', 'languageId', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ShopDefinition::class, 'language_version_id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', ShopDefinition::class, false),
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
