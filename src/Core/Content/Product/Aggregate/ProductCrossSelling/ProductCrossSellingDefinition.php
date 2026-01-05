<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductCrossSellingDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_cross_selling';
    final public const SORT_BY_PRICE = 'cheapestPrice';
    final public const SORT_BY_RELEASE_DATE = 'releaseDate';
    final public const SORT_BY_NAME = 'name';
    final public const TYPE_PRODUCT_STREAM = 'productStream';
    final public const TYPE_PRODUCT_LIST = 'productList';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductCrossSellingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductCrossSellingCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'position' => 0,
            'sortBy' => self::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'type' => self::TYPE_PRODUCT_STREAM,
            'active' => false,
            'limit' => 24,
        ];
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductCrossSellingHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of product cross selling.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new Required()),
            (new IntField('position', 'position', 0))->addFlags(new ApiAware(), new Required())->setDescription('The order of the tabs of your defined cross-selling actions in the storefront by entering numerical values like 1,2,3, etc.'),
            (new StringField('sort_by', 'sortBy'))->addFlags(new ApiAware())->setDescription('To sort the display of products by name, price or publication (descending, ascending) date.'),
            (new StringField('sort_direction', 'sortDirection'))->addFlags(new ApiAware())->setDescription('To sort the display of products by ascending or descending order.'),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('Type of product assignment for cross-selling. It can either be Dynamic product group or Manual assignment.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When set to active, the cross-selling feature is enabled.'),
            (new IntField('limit', 'limit', 0))->addFlags(new ApiAware())->setDescription('The maximum number of products to be displayed in cross-selling on the item detail page of your item.'),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required())->setDescription('Unique identity of product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class))->addFlags(new ReverseInherited('crossSellings')),

            (new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class))->setDescription('Unique identity of product stream.'),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class),
            (new OneToManyAssociationField('assignedProducts', ProductCrossSellingAssignedProductsDefinition::class, 'cross_selling_id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ProductCrossSellingTranslationDefinition::class, 'product_cross_selling_id'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
