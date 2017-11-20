<?php declare(strict_types=1);

namespace Shopware\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Category\Definition\CategoryDefinition;
use Shopware\Product\Event\ProductSeoCategory\ProductSeoCategoryWrittenEvent;
use Shopware\Shop\Definition\ShopDefinition;

class ProductSeoCategoryDefinition extends EntityDefinition
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

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        return self::$fields = new FieldCollection([
            (new FkField('shop_uuid', 'shopUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('product_uuid', 'productUuid', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('category_uuid', 'categoryUuid', CategoryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, false),
            new ManyToOneAssociationField('product', 'product_uuid', ProductDefinition::class, false),
            new ManyToOneAssociationField('category', 'category_uuid', CategoryDefinition::class, false),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own repositories');
    }

    public static function getBasicCollectionClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own collection classes');
    }

    public static function getBasicStructClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own struct classes');
    }

    public static function getWrittenEventClass(): string
    {
        return ProductSeoCategoryWrittenEvent::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
