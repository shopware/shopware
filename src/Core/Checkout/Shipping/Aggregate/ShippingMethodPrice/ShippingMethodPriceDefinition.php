<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ShippingMethodPriceDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'shipping_method_price';
    }

    public static function getCollectionClass(): string
    {
        return ShippingMethodPriceCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ShippingMethodPriceEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return ShippingMethodDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required()),

            (new FloatField('quantity_from', 'quantityFrom'))->addFlags(new Required()),
            (new FloatField('price', 'price'))->addFlags(new Required()),
            (new FloatField('factor', 'factor'))->addFlags(new Required()),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
        ]);
    }
}
