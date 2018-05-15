<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\System\Listing\Definition\ListingSortingDefinition;
use Shopware\Content\Product\Collection\ProductStreamBasicCollection;
use Shopware\Content\Product\Collection\ProductStreamDetailCollection;
use Shopware\Content\Product\Event\ProductStream\ProductStreamDeletedEvent;
use Shopware\Content\Product\Event\ProductStream\ProductStreamWrittenEvent;
use Shopware\Content\Product\Repository\ProductStreamRepository;
use Shopware\Content\Product\Struct\ProductStreamBasicStruct;
use Shopware\Content\Product\Struct\ProductStreamDetailStruct;

class ProductStreamDefinition extends EntityDefinition
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
        return 'product_stream';
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
            new CatalogField(),

            new FkField('listing_sorting_id', 'listingSortingId', ListingSortingDefinition::class),
            new ReferenceVersionField(ListingSortingDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new LongTextField('conditions', 'conditions'),
            new IntField('type', 'type'),
            (new LongTextField('description', 'description'))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new ManyToOneAssociationField('listingSorting', 'listing_sorting_id', ListingSortingDefinition::class, true))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'product_stream_id', false, 'id'))->setFlags(new WriteOnly()),
            (new ManyToManyAssociationField('productTabs', ProductDefinition::class, ProductStreamTabDefinition::class, false, 'product_stream_id', 'product_id', 'productTabIds'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductStreamAssignmentDefinition::class, false, 'product_stream_id', 'product_id', 'productIds'))->setFlags(new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductStreamRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductStreamBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductStreamDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductStreamWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductStreamBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductStreamDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductStreamDetailCollection::class;
    }
}
