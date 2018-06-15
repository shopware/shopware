<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Definition;

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
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationDeletedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationWrittenEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationRepository;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Struct\ListingFacetTranslationBasicStruct;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Struct\ListingFacetTranslationDetailStruct;
use Shopware\Core\System\Listing\ListingFacetDefinition;

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
