<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionRedemptionUpdater::class)]
class PromotionRedemptionUpdaterTest extends TestCase
{
    private Connection&MockObject $connectionMock;

    private PromotionRedemptionUpdater $promotionRedemptionUpdater;

    private EntityRepository&MockObject $orderRepositoryMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->orderRepositoryMock = $this->createMock(EntityRepository::class);
        $this->promotionRedemptionUpdater = new PromotionRedemptionUpdater($this->connectionMock, $this->orderRepositoryMock);
    }

    public function getDefinition(): OrderLineItemDefinition
    {
        new StaticDefinitionInstanceRegistry(
            [$definition = new OrderLineItemDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $definition;
    }

    public function testUpdateEmptyIds(): void
    {
        $this->connectionMock->expects(static::never())->method('fetchAllAssociative');
        $this->promotionRedemptionUpdater->update([], Context::createDefaultContext());
    }

    public function testUpdateValidCase(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn([
            ['promotion_id' => $promotionId, 'total' => 1, 'customer_id' => $customerId],
        ]);

        $statementMock = $this->createMock(Statement::class);
        $params = [
            ['id', Uuid::fromHexToBytes($promotionId)],
            ['count', 1],
            ['customerCount', json_encode([$customerId => 1], \JSON_THROW_ON_ERROR)],
        ];
        $matcher = static::exactly(\count($params));
        $statementMock->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statementMock->expects(static::once())
            ->method('executeStatement')
            ->willReturn(1);
        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->update([$promotionId], Context::createDefaultContext());
    }

    public function testOrderPlacedUpdatesPromotionsCorrectly(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $event = $this->createOrderPlacedEvent($promotionId, $customerId);

        $statementMock = $this->createMock(Statement::class);
        $params = [
            ['id', Uuid::fromHexToBytes($promotionId)],
            ['customerCount', json_encode([$customerId => 1], \JSON_THROW_ON_ERROR)],
            ['count', 0],
        ];
        $matcher = static::exactly(\count($params));
        $statementMock->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statementMock->expects(static::once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->orderUpdated($event);
    }

    public function testOrderPlacedNoLineItemsOrCustomer(): void
    {
        $event = $this->createOrderPlacedEvent(null, null);

        $this->connectionMock->expects(static::never())->method('fetchAllAssociative');
        $this->promotionRedemptionUpdater->orderUpdated($event);
    }

    public function testUpdateCalledBeforeOrderPlacedDoesNotRepeatUpdate(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                [
                    'promotion_id' => $promotionId,
                    'total' => 1,
                    'customer_id' => $customerId,
                ],
            ],
            [
                [
                    'promotion_id' => $promotionId,
                    'count' => 1,
                ],
            ],
            [
                [
                    'id' => Uuid::fromHexToBytes($promotionId),
                    'orders_per_customer_count' => json_encode([$customerId => 1]),
                ],
            ],
        );

        $statementMock = $this->createMock(Statement::class);
        $params = [
            ['id', Uuid::fromHexToBytes($promotionId)],
            ['count', 1],
            ['customerCount', json_encode([$customerId => 1], \JSON_THROW_ON_ERROR)],
        ];
        $matcher = static::exactly(\count($params));
        $statementMock->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statementMock->expects(static::once())->method('executeStatement')->willReturn(1);
        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->update([$promotionId], Context::createDefaultContext());

        $event = $this->createOrderPlacedEvent($promotionId, $customerId);

        // Expect no further update calls during orderPlaced
        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(static::never())->method('executeStatement');
        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->orderUpdated($event);
    }

    public function testBeforeDeletePromotionLineItems(): void
    {
        $customerId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $id = Uuid::randomBytes();
        $command = new DeleteCommand(
            $this->getDefinition(),
            ['id' => $id],
            $this->createMock(EntityExistence::class),
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        );

        $this->connectionMock->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                [
                    'promotion_id' => $promotionId,
                    'payload' => '{"code": "F1D6Y0X2"}',
                    'total' => 1,
                    'customer_id' => $customerId,
                ],
            ],
            [
                [
                    'id' => Uuid::fromHexToBytes($promotionId),
                    'orders_per_customer_count' => json_encode([$customerId => 1]),
                ],
            ]
        );

        $statementMock = $this->createMock(Statement::class);
        $params = [
            ['id', Uuid::fromHexToBytes($promotionId)],
            ['customerCount', json_encode([], \JSON_THROW_ON_ERROR)],
            ['orderCount', 1],
        ];
        $matcher = static::exactly(\count($params));
        $statementMock->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statementMock->expects(static::once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->connectionMock->method('prepare')->willReturn($statementMock);
        $this->connectionMock->expects(static::once())
            ->method('executeStatement')
            ->with(static::equalTo('UPDATE promotion_individual_code set payload = NULL WHERE code IN (:codes)'))
            ->willReturnCallback(function ($query, $params): int {
                static::assertSame(['codes' => ['F1D6Y0X2']], $params);

                return 1;
            });

        $this->promotionRedemptionUpdater->beforeDeletePromotionLineItems($event);

        $event->success();
    }

    public function testBeforeDeletePromotionLineItemsWithoutPromotion(): void
    {
        $id = Uuid::randomBytes();
        $command = new DeleteCommand(
            $this->getDefinition(),
            ['id' => $id],
            $this->createMock(EntityExistence::class),
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        );

        $this->connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn([]);

        $this->promotionRedemptionUpdater->beforeDeletePromotionLineItems($event);
    }

    public function testBeforeDeletePromotionLineItemsWithInsertCommand(): void
    {
        $command = new InsertCommand(
            $this->getDefinition(),
            ['order_id' => Uuid::randomBytes()],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        );

        $this->connectionMock->expects(static::never())->method('fetchAllAssociative');
        $this->promotionRedemptionUpdater->beforeDeletePromotionLineItems($event);
    }

    public function testBeforeDeletePromotionLineItemsWithUpdateCommand(): void
    {
        $command = new UpdateCommand(
            $this->getDefinition(),
            ['order_id' => Uuid::randomBytes()],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        );

        $this->connectionMock->expects(static::never())->method('fetchAllAssociative');
        $this->promotionRedemptionUpdater->beforeDeletePromotionLineItems($event);
    }

    public function testOrderUpdated(): void
    {
        $orderId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId(Uuid::randomHex());
        $orderLineItem->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $orderLineItem->setPromotionId($promotionId);

        $orderLineItems = new OrderLineItemCollection([$orderLineItem]);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setCustomerId($customerId);

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setLineItems($orderLineItems);
        $order->setOrderCustomer($orderCustomer);

        $entityWriteResult = new EntityWriteResult($orderId, [], 'order', EntityWriteResult::OPERATION_UPDATE);

        $orderCollection = new OrderCollection([$order]);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn($orderCollection);

        $this->orderRepositoryMock->method('search')->willReturn($entitySearchResult);

        $event = new EntityWrittenEvent(
            'order',
            [$entityWriteResult],
            Context::createDefaultContext()
        );

        $this->connectionMock->expects(static::once())
            ->method('prepare')
            ->with('UPDATE promotion SET order_count = :count, orders_per_customer_count = :customerCount WHERE id = :id')
            ->willReturn($this->createMock(Statement::class));

        $this->promotionRedemptionUpdater->orderUpdated($event);
    }

    private function createOrderPlacedEvent(?string $promotionId, ?string $customerId): EntityWrittenEvent
    {
        $lineItems = new OrderLineItemCollection();
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        if ($promotionId !== null) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId(Uuid::randomHex());
            $lineItem->setType(PromotionProcessor::LINE_ITEM_TYPE);
            $lineItem->setPromotionId($promotionId);
            $lineItems->add($lineItem);
            $order->setLineItems($lineItems);
        }

        if ($customerId !== null) {
            $orderCustomer = new OrderCustomerEntity();
            $orderCustomer->setId(Uuid::randomHex());
            $orderCustomer->setCustomerId($customerId);
            $order->setOrderCustomer($orderCustomer);
        }

        $context = Generator::generateSalesChannelContext();

        $criteria = new Criteria([$order->getId()]);
        $criteria->addAssociations(['lineItems', 'orderCustomer']);
        $result = new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, new OrderCollection([$order]), null, $criteria, $context->getContext());

        $this->orderRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $entityWriteResult = new EntityWriteResult($order->getId(), [], OrderDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE);

        return new EntityWrittenEvent(
            OrderDefinition::ENTITY_NAME,
            [$entityWriteResult],
            Context::createDefaultContext()
        );
    }
}
