<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationDefinition;

class ListingFacetDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'listing_facet';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('unique_key', 'uniqueKey'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            (new TranslatedField('name'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new BoolField('display_in_categories', 'displayInCategories'),
            new BoolField('deletable', 'deletable'),
            new IntField('position', 'position'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField(ListingFacetTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ListingFacetCollection::class;
    }

    public static function getStructClass(): string
    {
        return ListingFacetEntity::class;
    }
}
