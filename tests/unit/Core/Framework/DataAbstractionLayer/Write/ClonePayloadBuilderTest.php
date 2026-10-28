<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ClonePayloadBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClonePayloadBuilder::class)]
class ClonePayloadBuilderTest extends TestCase
{
    private ClonePayloadBuilder $builder;

    private EntityDefinition $orderDefinition;

    protected function setUp(): void
    {
        $this->builder = new ClonePayloadBuilder(new MockClock());

        $registry = new StaticDefinitionInstanceRegistry(
            [
                ClonePayloadOrderTestDefinition::class,
                ClonePayloadAddressTestDefinition::class,
                ClonePayloadDeliveryTestDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $this->orderDefinition = $registry->getByEntityName('clone_order');
    }

    public function testACopyPointsReferencesBetweenClonedRowsAtTheClone(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();
        $sourceDeliveryId = Uuid::randomHex();

        $payload = $this->builder->build(
            $this->orderDefinition,
            $this->orderLikeSource($sourceId, $sourceAddressId, $sourceDeliveryId),
            $sourceId,
            Uuid::randomHex(),
            [],
            Context::createDefaultContext(),
        );

        $clonedAddressId = $payload['addresses'][0]['id'];
        static::assertNotSame($sourceAddressId, $clonedAddressId);
        static::assertNotSame($sourceDeliveryId, $payload['deliveries'][0]['id']);

        // The root and the delivery referenced the source address; both reference the cloned address now.
        static::assertSame($clonedAddressId, $payload['billingAddressId']);
        static::assertSame($clonedAddressId, $payload['deliveries'][0]['shippingAddressId']);

        // The reference back to the root is left to the writer.
        static::assertArrayNotHasKey('orderId', $payload['deliveries'][0]);
    }

    public function testAVersionKeepsIdsAndReferences(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();
        $sourceDeliveryId = Uuid::randomHex();

        $payload = $this->builder->build(
            $this->orderDefinition,
            $this->orderLikeSource($sourceId, $sourceAddressId, $sourceDeliveryId),
            $sourceId,
            $sourceId,
            [],
            Context::createDefaultContext(),
        );

        static::assertSame($sourceId, $payload['id']);
        static::assertSame($sourceAddressId, $payload['addresses'][0]['id']);
        static::assertSame($sourceDeliveryId, $payload['deliveries'][0]['id']);
        static::assertSame($sourceAddressId, $payload['billingAddressId']);
        static::assertSame($sourceAddressId, $payload['deliveries'][0]['shippingAddressId']);
    }

    public function testAReferenceToARowOutsideTheCloneIsKept(): void
    {
        $sourceId = Uuid::randomHex();
        $foreignAddressId = Uuid::randomHex();

        $payload = $this->builder->build(
            $this->orderDefinition,
            [
                'id' => $sourceId,
                'billingAddressId' => $foreignAddressId,
                'addresses' => [['id' => Uuid::randomHex(), 'orderId' => $sourceId]],
            ],
            $sourceId,
            Uuid::randomHex(),
            [],
            Context::createDefaultContext(),
        );

        static::assertSame($foreignAddressId, $payload['billingAddressId']);
    }

    /**
     * An order-like source: the root and its delivery both reference one of the root's own addresses.
     *
     * @return array<string, mixed>
     */
    private function orderLikeSource(string $sourceId, string $addressId, string $deliveryId): array
    {
        return [
            'id' => $sourceId,
            'billingAddressId' => $addressId,
            'addresses' => [['id' => $addressId, 'orderId' => $sourceId]],
            'deliveries' => [['id' => $deliveryId, 'orderId' => $sourceId, 'shippingAddressId' => $addressId]],
        ];
    }
}

/**
 * @internal
 */
class ClonePayloadOrderTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_order';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('billing_address_id', 'billingAddressId', ClonePayloadAddressTestDefinition::class),
            (new OneToManyAssociationField('addresses', ClonePayloadAddressTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('deliveries', ClonePayloadDeliveryTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadAddressTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_address';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadDeliveryTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_delivery';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
            new FkField('shipping_address_id', 'shippingAddressId', ClonePayloadAddressTestDefinition::class),
        ]);
    }
}
