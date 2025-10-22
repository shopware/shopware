<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionRedemptionUpdater::class)]
class PromotionRedemptionUpdaterTest extends TestCase
{
    private Connection&MockObject $connectionMock;

    private MessageBusInterface&MockObject $messageBusMock;

    private PromotionRedemptionUpdater $promotionRedemptionUpdater;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->promotionRedemptionUpdater = new PromotionRedemptionUpdater($this->connectionMock);
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
        $this->connectionMock
            ->expects($this->never())
            ->method('fetchAllAssociative');

        $this->promotionRedemptionUpdater->update([], Context::createDefaultContext());
    }

    public function testNoLiveVersion(): void
    {
        $this->connectionMock
            ->expects($this->never())
            ->method('fetchAllAssociative');

        $this->promotionRedemptionUpdater->update([Uuid::randomHex()], Context::createDefaultContext()->createWithVersionId(Uuid::randomHex()));
    }

    public function testInvalidPromotionIds(): void
    {
        $this->connectionMock
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->messageBusMock
            ->expects($this->never())
            ->method('dispatch');

        $this->promotionRedemptionUpdater->update([Uuid::randomHex()], Context::createDefaultContext());
    }

    public function testItemDeleteNoLiveVersion(): void
    {
        $this->connectionMock
            ->expects($this->never())
            ->method('fetchFirstColumn');

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            []
        );

        $this->promotionRedemptionUpdater->beforeDelete($event);
    }

    public function testItemDeleteEmptyCommands(): void
    {
        $this->connectionMock
            ->expects($this->never())
            ->method('fetchFirstColumn');

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()->createWithVersionId(Uuid::randomHex())),
            []
        );

        $this->promotionRedemptionUpdater->beforeDelete($event);
    }

    public function testItemDelete(): void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([Uuid::randomHex()]);

        $this->connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $registry = new StaticDefinitionInstanceRegistry(
            [OrderLineItemDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $validInsertCommand = new DeleteCommand(
            $registry->get(OrderLineItemDefinition::class),
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
        );

        $updateCommand = new UpdateCommand(
            $registry->get(OrderLineItemDefinition::class),
            ['promotionId' => Uuid::randomHex()],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $writeEvent = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$validInsertCommand, $updateCommand]
        );

        $this->promotionRedemptionUpdater->beforeDelete($writeEvent);
        $this->promotionRedemptionUpdater->lineItemDeleted(new EntityDeletedEvent('order_line_item', [], Context::createDefaultContext()));
    }

    public function testUpdateValidCase(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $this->connectionMock
            ->method('fetchAllAssociative')
            ->willReturn(
                [
                    ['promotion_id' => $promotionId, 'total' => 0, 'customer_id' => null],
                    ['promotion_id' => $promotionId, 'total' => 1, 'customer_id' => $customerId],
                    ['promotion_id' => $promotionId, 'total' => 0, 'customer_id' => null],
                ]
            );

        $statementMock = $this->createMock(Statement::class);
        $params = [
            ['id', Uuid::fromHexToBytes($promotionId)],
            ['count', 1],
            ['customerCount', json_encode([$customerId => 1], \JSON_THROW_ON_ERROR)],
        ];
        $matcher = $this->exactly(\count($params));
        $statementMock->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statementMock
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);
        $this->connectionMock
            ->method('prepare')
            ->willReturn($statementMock);

        $this->promotionRedemptionUpdater->update([$promotionId], Context::createDefaultContext());
    }

    #[DataProvider('itemCreatedProvider')]
    public function testLineItemCreated(EntityWriteResult $writeResult, bool $shouldCalled): void
    {
        $this->connectionMock
            ->expects($shouldCalled ? $this->once() : $this->never())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->promotionRedemptionUpdater->lineItemCreated(new EntityWrittenEvent(
            'order_line_item',
            [$writeResult],
            Context::createDefaultContext()
        ));
    }

    /**
     * @return non-empty-list<array{EntityWriteResult, bool}>
     */
    public static function itemCreatedProvider(): array
    {
        return [
            [
                new EntityWriteResult('id', ['some-field' => 'some-value'], 'order_line_item', EntityWriteResult::OPERATION_INSERT),
                false,
            ], [
                new EntityWriteResult('id', ['promotionId' => null], 'order_line_item', EntityWriteResult::OPERATION_INSERT),
                false,
            ], [
                new EntityWriteResult('id', ['promotionId' => null, 'type' => 'some-type'], 'order_line_item', EntityWriteResult::OPERATION_INSERT),
                false,
            ], [
                new EntityWriteResult('id', ['promotionId' => null, 'type' => PromotionProcessor::LINE_ITEM_TYPE], 'order_line_item', EntityWriteResult::OPERATION_INSERT),
                false,
            ], [
                new EntityWriteResult('id', ['type' => PromotionProcessor::LINE_ITEM_TYPE], 'order_line_item', EntityWriteResult::OPERATION_INSERT),
                false,
            ], [
                new EntityWriteResult('id', ['promotionId' => Uuid::randomHex(), 'type' => PromotionProcessor::LINE_ITEM_TYPE], 'order_line_item', EntityWriteResult::OPERATION_INSERT),
                true,
            ], [
                new EntityWriteResult('id', ['promotionId' => Uuid::randomHex()], 'order_line_item', EntityWriteResult::OPERATION_UPDATE),
                false,
            ],
        ];
    }
}
