<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ListField;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationDefinition;

class ListingSortingDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'listing_sorting';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            new VersionField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new ListField('payload', 'payload', ObjectField::class))->setFlags(new Required()),
            (new TranslatedField(new StringField('label', 'label')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('unique_key', 'uniqueKey'))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new BoolField('display_in_categories', 'displayInCategories'),
            new IntField('position', 'position'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField('translations', ListingSortingTranslationDefinition::class, 'listing_sorting_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ListingSortingCollection::class;
    }

    public static function getStructClass(): string
    {
        return ListingSortingStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ListingSortingTranslationDefinition::class;
    }
}
