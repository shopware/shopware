<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\MappingEntityDefinition;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Content\Product\Event\ProductCategoryTree\ProductCategoryTreeDeletedEvent;
use Shopware\Content\Product\Event\ProductCategoryTree\ProductCategoryTreeWrittenEvent;

class ProductCategoryTreeDefinition extends MappingEntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    public static function getEntityName(): string
    {
        return 'product_category_tree';
    }

    public static function isVersionAware(): bool
    {
        return true;
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        return self::$fields = new FieldCollection([
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, false),
        ]);
    }

    public static function getWrittenEventClass(): string
    {
        return ProductCategoryTreeWrittenEvent::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductCategoryTreeDeletedEvent::class;
    }
}
