<?php declare(strict_types=1);

namespace Shopware\Api\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Order\Collection\OrderLineItemBasicCollection;
use Shopware\Api\Order\Collection\OrderLineItemDetailCollection;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemDeletedEvent;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemWrittenEvent;
use Shopware\Api\Order\Repository\OrderLineItemRepository;
use Shopware\Api\Order\Struct\OrderLineItemBasicStruct;
use Shopware\Api\Order\Struct\OrderLineItemDetailStruct;

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
