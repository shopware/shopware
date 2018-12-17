<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Listing\ListingFacetDefinition;

class ListingFacetTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'listing_facet_translation';
    }

    public static function getCollectionClass(): string
    {
        return ListingFacetTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ListingFacetTranslationEntity::class;
    }

    public static function getDefinition(): string
    {
        return ListingFacetDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
        ]);
    }
}
