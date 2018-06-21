<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Listing\ListingFacetDefinition;

class ListingFacetTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'listing_facet_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('listing_facet_id', 'listingFacetId', ListingFacetDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ListingFacetDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('listingFacet', 'listing_facet_id', ListingFacetDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ListingFacetTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return ListingFacetTranslationStruct::class;
    }
}
