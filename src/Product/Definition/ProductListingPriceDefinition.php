<?php declare(strict_types=1);

namespace Shopware\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Customer\Definition\CustomerGroupDefinition;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Product\Collection\ProductListingPriceDetailCollection;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceWrittenEvent;
use Shopware\Product\Repository\ProductListingPriceRepository;
use Shopware\Product\Struct\ProductListingPriceBasicStruct;
use Shopware\Product\Struct\ProductListingPriceDetailStruct;

class ProductListingPriceDefinition extends EntityDefinition
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
        return 'product_listing_price';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('product_uuid', 'productUuid', ProductDefinition::class))->setFlags(new Required()),
            (new FkField('customer_group_uuid', 'customerGroupUuid', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new FloatField('price', 'price'))->setFlags(new Required()),
            (new BoolField('display_from_price', 'displayFromPrice'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('product', 'product_uuid', ProductDefinition::class, false),
            new ManyToOneAssociationField('customerGroup', 'customer_group_uuid', CustomerGroupDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductListingPriceRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductListingPriceBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductListingPriceWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductListingPriceBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductListingPriceDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductListingPriceDetailCollection::class;
    }
}
