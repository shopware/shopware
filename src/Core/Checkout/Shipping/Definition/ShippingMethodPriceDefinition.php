<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Checkout\Shipping\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Checkout\Shipping\Collection\ShippingMethodPriceDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceDeletedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceWrittenEvent;
use Shopware\Checkout\Shipping\Repository\ShippingMethodPriceRepository;
use Shopware\Checkout\Shipping\Struct\ShippingMethodPriceBasicStruct;
use Shopware\Checkout\Shipping\Struct\ShippingMethodPriceDetailStruct;

class ShippingMethodPriceDefinition extends EntityDefinition
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
        return 'shipping_method_price';
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

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShippingMethodDefinition::class))->setFlags(new Required()),

            (new FloatField('quantity_from', 'quantityFrom'))->setFlags(new Required()),
            (new FloatField('price', 'price'))->setFlags(new Required()),
            (new FloatField('factor', 'factor'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShippingMethodPriceRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShippingMethodPriceBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ShippingMethodPriceDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShippingMethodPriceWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShippingMethodPriceBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShippingMethodPriceDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShippingMethodPriceDetailCollection::class;
    }
}
