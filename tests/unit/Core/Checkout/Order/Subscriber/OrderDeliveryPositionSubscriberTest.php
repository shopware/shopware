<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Subscriber\OrderDeliveryPositionSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderDeliveryPositionSubscriber::class)]
class OrderDeliveryPositionSubscriberTest extends TestCase
{
    private const DRAFT_VERSION = '0190c8d2f3e57b4d8c2a1e6f9b3d5a7c';

    private OrderDeliveryDefinition $definition;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        new StaticDefinitionInstanceRegistry(
            [$this->definition = new OrderDeliveryDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testDeliveriesWrittenWithoutPositionGetTheNextFreePositionOfTheirOrder(): void
    {
        $added = $this->insert('added', 'order');
        $secondAdded = $this->insert('second-added', 'order');
        $addedToDraft = $this->insert('added-to-draft', 'order', self::DRAFT_VERSION);
        $addedToOtherOrder = $this->insert('added-to-other-order', 'other-order');

        $this->assignPositions([$added, $secondAdded, $addedToDraft, $addedToOtherOrder], [
            $this->orderKey('order') => '2',
            $this->orderKey('order', self::DRAFT_VERSION) => '3',
        ]);

        static::assertSame(3, $added->getPayload()['position']);
        static::assertSame(4, $secondAdded->getPayload()['position']);
        static::assertSame(4, $addedToDraft->getPayload()['position']);
        static::assertSame(1, $addedToOtherOrder->getPayload()['position']);
    }

    public function testDeliveriesWrittenWithoutPositionGoAfterThoseWrittenWithOne(): void
    {
        $withPosition = $this->insert('with-position', 'order', position: 5);
        $withoutPosition = $this->insert('without-position', 'order');

        $this->assignPositions([$withPosition, $withoutPosition], [$this->orderKey('order') => '2']);

        static::assertSame(5, $withPosition->getPayload()['position']);
        static::assertSame(6, $withoutPosition->getPayload()['position']);
    }

    public function testLeavesUpdatesAndDeliveriesWrittenWithPositionAlone(): void
    {
        $update = new UpdateCommand(
            $this->definition,
            ['tracking_codes' => '[]'],
            ['id' => $this->ids->getBytes('placed')],
            static::createStub(EntityExistence::class),
            '/0'
        );
        $withPosition = $this->insert('with-position', 'order', position: 2);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        (new OrderDeliveryPositionSubscriber($connection))->assignNextFreePosition(EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$update, $withPosition],
        ));

        static::assertArrayNotHasKey('position', $update->getPayload());
        static::assertSame(2, $withPosition->getPayload()['position']);
    }

    /**
     * @param list<WriteCommand> $commands
     * @param array<string, string> $storedLastPositions
     */
    private function assignPositions(array $commands, array $storedLastPositions): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($storedLastPositions);

        (new OrderDeliveryPositionSubscriber($connection))->assignNextFreePosition(EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands,
        ));
    }

    private function insert(string $delivery, string $order, string $orderVersionId = Defaults::LIVE_VERSION, ?int $position = null): InsertCommand
    {
        $payload = [
            'id' => $this->ids->getBytes($delivery),
            'order_id' => $this->ids->getBytes($order),
            'order_version_id' => Uuid::fromHexToBytes($orderVersionId),
        ];

        if ($position !== null) {
            $payload['position'] = $position;
        }

        return new InsertCommand($this->definition, $payload, ['id' => $this->ids->getBytes($delivery)], static::createStub(EntityExistence::class), '/0');
    }

    private function orderKey(string $order, string $orderVersionId = Defaults::LIVE_VERSION): string
    {
        return $this->ids->getBytes($order) . Uuid::fromHexToBytes($orderVersionId);
    }
}
