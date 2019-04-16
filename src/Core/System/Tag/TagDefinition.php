<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class TagDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'tag';
    }

    public static function getCollectionClass(): string
    {
        return TagCollection::class;
    }

    public static function getEntityClass(): string
    {
        return TagEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            new UpdatedAtField(),

            new ManyToManyAssociationField('products', ProductDefinition::class, ProductTagDefinition::class, 'tag_id', 'product_id'),
            new ManyToManyAssociationField('media', MediaDefinition::class, MediaTagDefinition::class, 'tag_id', 'media_id'),
            new ManyToManyAssociationField('categories', CategoryDefinition::class, CategoryTagDefinition::class, 'tag_id', 'category_id'),
            new ManyToManyAssociationField('customers', CustomerDefinition::class, CustomerTagDefinition::class, 'tag_id', 'customer_id'),
            new ManyToManyAssociationField('orders', OrderDefinition::class, OrderTagDefinition::class, 'tag_id', 'order_id'),
            new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, ShippingMethodTagDefinition::class, 'tag_id', 'shipping_method_id'),
        ]);
    }
}
