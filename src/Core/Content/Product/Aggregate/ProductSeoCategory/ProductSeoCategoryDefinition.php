<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSeoCategory;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\Event\ProductSeoCategoryDeletedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\Event\ProductSeoCategoryWrittenEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\MappingEntityDefinition;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;

class ProductSeoCategoryDefinition extends MappingEntityDefinition
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
        return 'product_seo_category';
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
            (new FkField('touchpoint_id', 'touchpointId', TouchpointDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('touchpoint', 'touchpoint_id', TouchpointDefinition::class, false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, false),
        ]);
    }

    public static function getWrittenEventClass(): string
    {
        return ProductSeoCategoryWrittenEvent::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductSeoCategoryDeletedEvent::class;
    }
}
