<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionCollector::class)]
class PromotionCollectorTest extends TestCase
{
    private readonly PromotionGatewayInterface&MockObject $gateway;

    private readonly PromotionCollector $promotionCollector;

    private readonly SalesChannelContext&MockObject $context;

    private readonly Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(PromotionGatewayInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->promotionCollector = new PromotionCollector(
            $this->gateway,
            new PromotionItemBuilder(),
            $this->createMock(HtmlSanitizer::class),
            $this->connection
        );

        $this->context = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $this->context->method('getCustomer')->willReturn($customer);
    }

    public function testCollectWithExistingPromotionAndDifferentDiscount(): void
    {
        $discountId1 = Uuid::randomHex();
        $discountId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $cart = $this->prepareCart([$discountId1, $discountId2], $promotionId);
        $cartDataCollection = new CartDataCollection();

        $this->promotionCollector->collect($cartDataCollection, $cart, $this->context, new CartBehavior());

        /** @var LineItemCollection $promotions */
        $promotions = $cartDataCollection->get(PromotionProcessor::DATA_KEY);
        $promotionFirst = $promotions->first();
        $promotionLast = $promotions->last();

        static::assertNotNull($promotionFirst);
        static::assertNotNull($promotionLast);

        static::assertCount(2, $promotions);
        static::assertSame($promotionId, $promotionFirst->getPayloadValue('promotionId'));
        static::assertSame($discountId1, $promotionFirst->getPayloadValue('discountId'));
        static::assertNotNull($promotionFirst->getExtension(OrderConverter::ORIGINAL_ID));

        static::assertSame($promotionId, $promotionLast->getPayloadValue('promotionId'));
        static::assertSame($discountId2, $promotionLast->getPayloadValue('discountId'));
        static::assertNull($promotionLast->getExtension(OrderConverter::ORIGINAL_ID));
    }

    public function testPromotionWithInvalidOrderCount(): void
    {
        $cart = $this->prepareCart([Uuid::randomHex(), Uuid::randomHex()], Uuid::randomHex(), 2, 1);
        $cartDataCollection = new CartDataCollection();

        $this->promotionCollector->collect($cartDataCollection, $cart, $this->context, new CartBehavior());

        static::assertNull($cartDataCollection->get(PromotionProcessor::DATA_KEY));
    }

    public function testPromotionWithInvalidOrderCountPerCustomerCount(): void
    {
        $cart = $this->prepareCart([Uuid::randomHex(), Uuid::randomHex()], Uuid::randomHex(), 1, 2, 1, [$this->context->getCustomer()?->getId() => 1]);
        $cartDataCollection = new CartDataCollection();

        $this->promotionCollector->collect($cartDataCollection, $cart, $this->context, new CartBehavior());

        static::assertNull($cartDataCollection->get(PromotionProcessor::DATA_KEY));
    }

    public function testPromotionWithoutDiscount(): void
    {
        $code = 'promotions-code';

        $lineItem1 = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex());

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem1]));

        $promotion = $this->createPromotion(Uuid::randomHex(), $code, []);

        $this->gateway->method('get')->willReturn(
            new PromotionCollection([$promotion]),
            new PromotionCollection(),
        );

        $cartDataCollection = new CartDataCollection();

        $this->promotionCollector->collect($cartDataCollection, $cart, $this->context, new CartBehavior());

        static::assertNull($cartDataCollection->get(PromotionProcessor::DATA_KEY));
    }

    public function testPromotionWithMaxTotalUseIsReachedInEditingOrder(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('1');
        $discountId1 = Uuid::randomHex();
        $discountId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $cart = $this->prepareCart([$discountId1, $discountId2], $promotionId, 1);
        $cart->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct(Uuid::randomHex()));
        $cartDataCollection = new CartDataCollection();

        $this->promotionCollector->collect($cartDataCollection, $cart, $this->context, new CartBehavior());

        /** @var LineItemCollection $promotions */
        $promotions = $cartDataCollection->get(PromotionProcessor::DATA_KEY);
        $promotionFirst = $promotions->first();
        $promotionLast = $promotions->last();

        static::assertNotNull($promotionFirst);
        static::assertNotNull($promotionLast);

        static::assertCount(2, $promotions);
        static::assertSame($promotionId, $promotionFirst->getPayloadValue('promotionId'));
        static::assertSame($discountId1, $promotionFirst->getPayloadValue('discountId'));
        static::assertNotNull($promotionFirst->getExtension(OrderConverter::ORIGINAL_ID));

        static::assertSame($promotionId, $promotionLast->getPayloadValue('promotionId'));
        static::assertSame($discountId2, $promotionLast->getPayloadValue('discountId'));
        static::assertNull($promotionLast->getExtension(OrderConverter::ORIGINAL_ID));
    }

    public function testPromotionWithMaxUsePerCustomerIsReachedInEditingOrder(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('1');
        $discountId1 = Uuid::randomHex();
        $discountId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $cart = $this->prepareCart(
            [$discountId1, $discountId2],
            $promotionId,
            1,
            1,
            1,
            [$this->context->getCustomer()?->getId() => 1]
        );
        $cart->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct(Uuid::randomHex()));

        $cartDataCollection = new CartDataCollection();

        $this->promotionCollector->collect($cartDataCollection, $cart, $this->context, new CartBehavior());

        /** @var LineItemCollection $promotions */
        $promotions = $cartDataCollection->get(PromotionProcessor::DATA_KEY);
        $promotionFirst = $promotions->first();
        $promotionLast = $promotions->last();

        static::assertNotNull($promotionFirst);
        static::assertNotNull($promotionLast);

        static::assertCount(2, $promotions);
        static::assertSame($promotionId, $promotionFirst->getPayloadValue('promotionId'));
        static::assertSame($discountId1, $promotionFirst->getPayloadValue('discountId'));
        static::assertNotNull($promotionFirst->getExtension(OrderConverter::ORIGINAL_ID));

        static::assertSame($promotionId, $promotionLast->getPayloadValue('promotionId'));
        static::assertSame($discountId2, $promotionLast->getPayloadValue('discountId'));
        static::assertNull($promotionLast->getExtension(OrderConverter::ORIGINAL_ID));
    }

    /**
     * @param string[] $discountIds
     * @param array<string, int>|null $orderPerCustomerCount
     */
    private function prepareCart(
        array $discountIds,
        string $promotionId,
        int $orderCount = 1,
        ?int $maxTotalUse = null,
        ?int $maxUsePerCustomer = null,
        ?array $orderPerCustomerCount = null
    ): Cart {
        $code = 'promotions-code';

        $lineItem1 = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex());

        $lineItemId2 = Uuid::randomHex();
        $lineItem2 = new LineItem($lineItemId2, LineItem::DISCOUNT_LINE_ITEM, $code);
        $lineItem2->setPayloadValue('discountId', $discountIds[0]);
        $lineItem2->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($lineItemId2));

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem1, $lineItem2]));

        $promotion = $this->createPromotion($promotionId, $code, $discountIds, $orderCount, $maxTotalUse, $maxUsePerCustomer, $orderPerCustomerCount);

        $promotionData = new CartExtension();
        $promotionData->addCode($code);
        $cart->addExtension(CartExtension::KEY, $promotionData);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $this->context->method('getSalesChannel')->willReturn($salesChannel);

        $this->gateway->method('get')->willReturn(
            new PromotionCollection([$promotion]),
            new PromotionCollection(),
        );

        return $cart;
    }

    /**
     * @param string[] $ids
     */
    private function createPromotionDiscountCollection(array $ids, PromotionEntity $promotion): PromotionDiscountCollection
    {
        $discounts = [];
        foreach ($ids as $id) {
            $discount = new PromotionDiscountEntity();
            $discount->setId($id);
            $discount->setScope(PromotionDiscountEntity::SCOPE_CART);
            $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
            $discount->setValue(10.0);
            $discount->setPromotionId($promotion->getId());

            $discounts[] = $discount;
        }

        return new PromotionDiscountCollection($discounts);
    }

    /**
     * @param string[] $discountIds
     * @param array<string, int>|null $orderPerCustomerCount
     */
    private function createPromotion(
        string $promotionId,
        string $code,
        array $discountIds,
        int $orderCount = 1,
        ?int $maxTotalUse = null,
        ?int $maxUsePerCustomer = null,
        ?array $orderPerCustomerCount = null
    ): PromotionEntity {
        $promotion = new PromotionEntity();
        $promotion->setId($promotionId);
        $promotion->setCode($code);
        $promotion->setUseIndividualCodes(true);
        $promotion->setPriority(1);
        $promotion->setOrderCount($orderCount);

        $promotion->setDiscounts($this->createPromotionDiscountCollection($discountIds, $promotion));

        if ($maxTotalUse !== null) {
            $promotion->setMaxRedemptionsGlobal($maxTotalUse);
        }

        if ($maxUsePerCustomer !== null) {
            $promotion->setMaxRedemptionsPerCustomer($maxUsePerCustomer);
        }

        if ($orderPerCustomerCount !== null) {
            $promotion->setOrdersPerCustomerCount($orderPerCustomerCount);
        }

        return $promotion;
    }
}
