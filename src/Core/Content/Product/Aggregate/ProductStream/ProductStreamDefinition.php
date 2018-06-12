<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamAssignment\ProductStreamAssignmentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamTab\ProductStreamTabDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\System\Listing\ListingSortingDefinition;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
    }

    public static function getCollectionClass(): string
    {
        return ProductStreamCollection::class;
    }

    public static function getStructClass(): string
    {
        return ProductStreamStruct::class;
    }
}
