<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Customer\Definition\CustomerGroupDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Collection\ProductPriceBasicCollection;
use Shopware\Api\Product\Collection\ProductPriceDetailCollection;
use Shopware\Api\Product\Event\ProductPrice\ProductPriceDeletedEvent;
use Shopware\Api\Product\Event\ProductPrice\ProductPriceWrittenEvent;
use Shopware\Api\Product\Repository\ProductPriceRepository;
use Shopware\Api\Product\Struct\ProductPriceBasicStruct;
use Shopware\Api\Product\Struct\ProductPriceDetailStruct;

class ProductPriceDefinition extends EntityDefinition
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
        return 'product_price';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            new IntField('quantity_start', 'quantityStart'),
            new IntField('quantity_end', 'quantityEnd'),
            new FloatField('price', 'price'),
            new FloatField('pseudo_price', 'pseudoPrice'),
            new FloatField('base_price', 'basePrice'),
            new FloatField('percentage', 'percentage'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, true),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductPriceRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductPriceBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductPriceDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductPriceWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductPriceBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductPriceDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductPriceDetailCollection::class;
    }
}
