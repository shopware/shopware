<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Checkout\Order\Collection\OrderLineItemBasicCollection;
use Shopware\Checkout\Order\Collection\OrderLineItemDetailCollection;
use Shopware\Checkout\Order\Event\OrderLineItem\OrderLineItemDeletedEvent;
use Shopware\Checkout\Order\Event\OrderLineItem\OrderLineItemWrittenEvent;
use Shopware\Checkout\Order\Repository\OrderLineItemRepository;
use Shopware\Checkout\Order\Struct\OrderLineItemBasicStruct;
use Shopware\Checkout\Order\Struct\OrderLineItemDetailStruct;

class OrderLineItemDefinition extends EntityDefinition
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
        return 'order_line_item';
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

            (new FkField('order_id', 'orderId', OrderDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->setFlags(new Required()),

            (new StringField('identifier', 'identifier'))->setFlags(new Required()),
            (new IntField('quantity', 'quantity'))->setFlags(new Required()),
            (new FloatField('unit_price', 'unitPrice'))->setFlags(new Required()),
            (new FloatField('total_price', 'totalPrice'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('parent_id', 'parentId'),
            new StringField('type', 'type'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false),
            (new OneToManyAssociationField('orderDeliveryPositions', OrderDeliveryPositionDefinition::class, 'order_line_item_id', false, 'id'))->setFlags(new CascadeDelete(), new ReadOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderLineItemRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderLineItemBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderLineItemDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderLineItemWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderLineItemBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderLineItemDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderLineItemDetailCollection::class;
    }
}
