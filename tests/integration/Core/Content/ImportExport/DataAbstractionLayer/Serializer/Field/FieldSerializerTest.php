<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEmptyValidOneToManyAssociation(): void
    {
        $fieldSerializer = $this->getFieldSerializer();

        $config = new Config([], [], []);

        $field = new OneToManyAssociationField('deliveries', OrderDeliveryEntity::class, 'order_delivery');
        $registry = new DefinitionInstanceRegistry(static::getContainer(), [OrderDeliveryEntity::class => OrderDeliveryDefinition::class], []);
        $field->compile($registry);

        $result = \iterator_to_array($fieldSerializer->serialize($config, $field, []));
        static::assertSame(['deliveries' => []], $result);
    }

    public function testValidOneToManyAssociation(): void
    {
        $fieldSerializer = $this->getFieldSerializer();

        $config = new Config([], [], []);

        $field = new OneToManyAssociationField('deliveries', OrderDeliveryEntity::class, 'order_delivery');
        $registry = new DefinitionInstanceRegistry(static::getContainer(), [OrderDeliveryEntity::class => OrderDeliveryDefinition::class], []);
        $field->compile($registry);

        $delivery = new OrderDeliveryEntity();
        $deliveryId = Uuid::randomHex();
        $delivery->setId($deliveryId);
        $delivery->setOrderId('order-id');
        $delivery->setOrderVersionId('order-version-id');
        $delivery->setShippingMethodId('shipping-method-id');
        $delivery->setShippingOrderAddressId('shipping-order-address-id');
        $delivery->setShippingOrderAddressVersionId('shipping-order-address-version-id');
        $delivery->setTrackingCodes([]);
        $delivery->setStateId('state-id');

        $result = \iterator_to_array($fieldSerializer->serialize($config, $field, new OrderDeliveryCollection([$delivery])));
        static::assertSame([
            'deliveries' => [
                '_uniqueIdentifier' => $deliveryId,
                'versionId' => null,
                'translated' => [],
                'orderId' => 'order-id',
                'orderVersionId' => 'order-version-id',
                'shippingOrderAddressId' => 'shipping-order-address-id',
                'shippingOrderAddressVersionId' => 'shipping-order-address-version-id',
                'shippingMethodId' => 'shipping-method-id',
                'trackingCodes' => '[]',
                'stateId' => 'state-id',
                'customFields' => null,
                'id' => $deliveryId,
            ],
        ], $result);
    }

    public function testInvalidEntityOneToManyAssociation(): void
    {
        $fieldSerializer = $this->getFieldSerializer();

        $config = new Config([], [], []);

        $field = new OneToManyAssociationField('deliveries', OrderEntity::class, 'order_delivery');
        $registry = new DefinitionInstanceRegistry(static::getContainer(), [OrderEntity::class => OrderDefinition::class], []);
        $field->compile($registry);

        $delivery = new OrderDeliveryEntity();
        $deliveryId = Uuid::randomHex();
        $delivery->setId($deliveryId);

        $result = \iterator_to_array($fieldSerializer->serialize($config, $field, new OrderDeliveryCollection([$delivery])));
        static::assertEmpty($result);
    }

    public function testInvalidPropertyNameOneToManyAssociation(): void
    {
        $fieldSerializer = $this->getFieldSerializer();

        $config = new Config([], [], []);

        $field = new OneToManyAssociationField('foo', OrderEntity::class, 'order_delivery');
        $registry = new DefinitionInstanceRegistry(static::getContainer(), [OrderEntity::class => OrderDefinition::class], []);
        $field->compile($registry);

        $delivery = new OrderDeliveryEntity();
        $deliveryId = Uuid::randomHex();
        $delivery->setId($deliveryId);

        $result = \iterator_to_array($fieldSerializer->serialize($config, $field, new OrderDeliveryCollection([$delivery])));
        static::assertEmpty($result);
    }

    public function testNullValueOneToManyAssociation(): void
    {
        $fieldSerializer = $this->getFieldSerializer();

        $config = new Config([], [], []);

        $field = new OneToManyAssociationField('deliveries', OrderEntity::class, 'order_delivery');
        $registry = new DefinitionInstanceRegistry(static::getContainer(), [OrderEntity::class => OrderDefinition::class], []);
        $field->compile($registry);

        $result = \iterator_to_array($fieldSerializer->serialize($config, $field, null));
        static::assertEmpty($result);
    }

    private function getFieldSerializer(): FieldSerializer
    {
        $fieldSerializer = new FieldSerializer();
        $serializerRegistry = new SerializerRegistry([new EntitySerializer()], [$fieldSerializer]);
        $fieldSerializer->setRegistry($serializerRegistry);

        return $fieldSerializer;
    }
}
