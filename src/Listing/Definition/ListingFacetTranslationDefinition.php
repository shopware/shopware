<?php declare(strict_types=1);

namespace Shopware\Listing\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Listing\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationWrittenEvent;
use Shopware\Listing\Repository\ListingFacetTranslationRepository;
use Shopware\Listing\Struct\ListingFacetTranslationBasicStruct;
use Shopware\Listing\Struct\ListingFacetTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

class ListingFacetTranslationDefinition extends EntityDefinition
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
        return 'listing_facet_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('listing_facet_uuid', 'listingFacetUuid', ListingFacetDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('listingFacet', 'listing_facet_uuid', ListingFacetDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ListingFacetTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ListingFacetTranslationBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ListingFacetTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ListingFacetTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ListingFacetTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ListingFacetTranslationDetailCollection::class;
    }
}
