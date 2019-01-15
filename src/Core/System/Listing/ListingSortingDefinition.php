<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationDefinition;

class ListingSortingDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'listing_sorting';
    }

    public static function getCollectionClass(): string
    {
        return ListingSortingCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ListingSortingEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new ListField('payload', 'payload', ObjectField::class))->setFlags(new Required()),
            (new TranslatedField('label'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('unique_key', 'uniqueKey'))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new BoolField('display_in_categories', 'displayInCategories'),
            new IntField('position', 'position'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField(ListingSortingTranslationDefinition::class, 'listing_sorting_id'))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }
}
