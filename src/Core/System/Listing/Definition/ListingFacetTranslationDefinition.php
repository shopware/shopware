<?php declare(strict_types=1);

namespace Shopware\System\Listing\Definition;

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
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\System\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\System\Listing\Collection\ListingFacetTranslationDetailCollection;
use Shopware\System\Listing\Event\ListingFacetTranslation\ListingFacetTranslationDeletedEvent;
use Shopware\System\Listing\Event\ListingFacetTranslation\ListingFacetTranslationWrittenEvent;
use Shopware\System\Listing\Repository\ListingFacetTranslationRepository;
use Shopware\System\Listing\Struct\ListingFacetTranslationBasicStruct;
use Shopware\System\Listing\Struct\ListingFacetTranslationDetailStruct;

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
            (new FkField('listing_facet_id', 'listingFacetId', ListingFacetDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ListingFacetDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('listingFacet', 'listing_facet_id', ListingFacetDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
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

    public static function getDeletedEventClass(): string
    {
        return ListingFacetTranslationDeletedEvent::class;
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
