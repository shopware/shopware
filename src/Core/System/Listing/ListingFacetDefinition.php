<?php declare(strict_types=1);

namespace Shopware\System\Listing;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Listing\Collection\ListingFacetBasicCollection;
use Shopware\System\Listing\Collection\ListingFacetDetailCollection;
use Shopware\System\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\System\Listing\Event\ListingFacetDeletedEvent;
use Shopware\System\Listing\Event\ListingFacetWrittenEvent;
use Shopware\System\Listing\ListingFacetRepository;
use Shopware\System\Listing\Struct\ListingFacetBasicStruct;
use Shopware\System\Listing\Struct\ListingFacetDetailStruct;

class ListingFacetDefinition extends EntityDefinition
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
        return 'listing_facet';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new StringField('unique_key', 'uniqueKey'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new BoolField('display_in_categories', 'displayInCategories'),
            new BoolField('deletable', 'deletable'),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslationsAssociationField('translations', ListingFacetTranslationDefinition::class, 'listing_facet_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ListingFacetRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ListingFacetBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ListingFacetDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ListingFacetWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ListingFacetBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ListingFacetTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ListingFacetDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ListingFacetDetailCollection::class;
    }
}
