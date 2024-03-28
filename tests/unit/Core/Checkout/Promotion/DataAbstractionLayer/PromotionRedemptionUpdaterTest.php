<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionRedemptionUpdater::class)]
class PromotionRedemptionUpdaterTest extends TestCase
{
    private Connection&MockObject $connectionMock;

    private PromotionRedemptionUpdater $promotionRedemptionUpdater;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->promotionRedemptionUpdater = new PromotionRedemptionUpdater($this->connectionMock);
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
        $statementMock->expects(static::once())
            ->method('executeStatement')
            ->with(static::equalTo([
                'id' => Uuid::fromHexToBytes($promotionId),
                'customerCount' => json_encode([$customerId => 1], \JSON_THROW_ON_ERROR),
                'count' => 1,
            ]));
        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->update([$promotionId], Context::createDefaultContext());
    }

    public function testOrderPlacedUpdatesPromotionsCorrectly(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $event = $this->createOrderPlacedEvent($promotionId, $customerId);

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(static::once())
            ->method('executeStatement')
            ->with(static::equalTo([
                'id' => Uuid::fromHexToBytes($promotionId),
                'customerCount' => json_encode([$customerId => 1], \JSON_THROW_ON_ERROR),
            ]));

        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->orderPlaced($event);
    }

    public function testOrderPlacedNoLineItemsOrCustomer(): void
    {
        $event = $this->createOrderPlacedEvent(null, null);

        $this->connectionMock->expects(static::never())->method('fetchAllAssociative');
        $this->promotionRedemptionUpdater->orderPlaced($event);
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
                    'id' => Uuid::fromHexToBytes($promotionId),
                    'orders_per_customer_count' => json_encode([$customerId => 1]),
                ],
            ]
        );

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(static::once())->method('executeStatement')->with([
            'id' => Uuid::fromHexToBytes($promotionId),
            'customerCount' => json_encode([$customerId => 1], \JSON_THROW_ON_ERROR),
            'count' => 1,
        ]);
        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->update([$promotionId], Context::createDefaultContext());

        $event = $this->createOrderPlacedEvent($promotionId, $customerId);

        // Expect no further update calls during orderPlaced
        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(static::never())->method('executeStatement');
        $this->connectionMock->method('prepare')->willReturn($statementMock);

        $this->promotionRedemptionUpdater->orderPlaced($event);
    }

    private function createOrderPlacedEvent(?string $promotionId, ?string $customerId): CheckoutOrderPlacedEvent
    {
        $lineItems = new OrderLineItemCollection();
        $order = new OrderEntity();
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

        $event = new CheckoutOrderPlacedEvent(
            Context::createDefaultContext(),
            $order,
            TestDefaults::SALES_CHANNEL,
        );

        return $event;
    }
}
