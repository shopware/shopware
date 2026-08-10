<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CreditItemResolver::class)]
class CreditItemResolverTest extends TestCase
{
    public function testResolveReturnsEveryCreditWhenNoneWereProcessed(): void
    {
        $creditA = $this->createCredit();
        $creditB = $this->createCredit();
        $order = $this->createOrder([$this->createProduct(), $creditA, $creditB]);

        $unprocessed = $this->createResolver([], [])->resolve($order, Uuid::randomHex());

        static::assertSame([$creditA, $creditB], array_values($unprocessed->getElements()));
    }

    public function testResolveExcludesCreditsAlreadyCarriedByTheInvoice(): void
    {
        $creditA = $this->createCredit();
        $creditB = $this->createCredit();
        $order = $this->createOrder([$creditA, $creditB]);

        $unprocessed = $this
            ->createResolver([Uuid::fromHexToBytes($creditA->getId())], [])
            ->resolve($order, Uuid::randomHex());

        static::assertSame([$creditB], array_values($unprocessed->getElements()));
    }

    public function testResolveExcludesCreditsAlreadyOnAPriorCreditNote(): void
    {
        $creditA = $this->createCredit();
        $creditB = $this->createCredit();
        $order = $this->createOrder([$creditA, $creditB]);

        $unprocessed = $this
            ->createResolver([], [Uuid::fromHexToBytes($creditB->getId())])
            ->resolve($order, Uuid::randomHex());

        static::assertSame([$creditA], array_values($unprocessed->getElements()));
    }

    public function testResolveThrowsWithoutQueryingWhenTheOrderHasNoCreditItems(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $order = $this->createOrder([$this->createProduct()]);

        $this->expectExceptionObject(DocumentV2Exception::noCreditLineItems($order->getId()));

        (new CreditItemResolver($connection))->resolve($order, Uuid::randomHex());
    }

    public function testResolveThrowsWhenEveryCreditIsAlreadyProcessed(): void
    {
        $creditA = $this->createCredit();
        $creditB = $this->createCredit();
        $order = $this->createOrder([$creditA, $creditB]);

        $resolver = $this->createResolver(
            [Uuid::fromHexToBytes($creditA->getId())],
            [Uuid::fromHexToBytes($creditB->getId())],
        );

        $this->expectExceptionObject(DocumentV2Exception::noUnprocessedCreditLineItems($order->getId()));

        $resolver->resolve($order, Uuid::randomHex());
    }

    /**
     * @param list<string> $invoicedCreditIds binary ids returned for the referenced-invoice query
     * @param list<string> $priorlyCreditedIds binary ids returned for the prior-credit-note query
     */
    private function createResolver(array $invoicedCreditIds, array $priorlyCreditedIds): CreditItemResolver
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls($invoicedCreditIds, $priorlyCreditedIds);

        return new CreditItemResolver($connection);
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createCredit(): OrderLineItemEntity
    {
        return $this->createLineItem(LineItem::CREDIT_LINE_ITEM_TYPE);
    }

    private function createProduct(): OrderLineItemEntity
    {
        return $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE);
    }

    private function createLineItem(string $type): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setId(Uuid::randomHex());
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setType($type);
        $item->setLabel('Item');
        $item->setQuantity(1);
        $item->setPosition(1);

        return $item;
    }
}
