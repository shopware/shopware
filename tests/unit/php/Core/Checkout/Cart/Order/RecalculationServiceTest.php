<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Order\RecalculationService
 */
class RecalculationServiceTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
    }

    public function testRecalculateOrderWithTaxStatus(): void
    {
        $context = Context::createDefaultContext();

        $orderId = Uuid::randomHex();

        $cart = new Cart(Uuid::randomHex());
        $cart->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            $this->createMock(CalculatedTaxCollection::class),
            $this->createMock(TaxRuleCollection::class),
            CartPrice::TAX_STATE_FREE
        ));

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data, Context $context) {
                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);
                /** @var CartPrice $price */
                $price = $data[0]['price'];

                static::assertSame($price->getTaxStatus(), CartPrice::TAX_STATE_FREE);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
                ]), []);
            });

        $orderConverter = $this->createMock(OrderConverter::class);

        $orderConverter
            ->expects(static::once())
            ->method('assembleSalesChannelContext')
            ->willReturnCallback(function (OrderEntity $order, Context $context) {
                $context->setTaxState($order->getTaxStatus());

                return SalesChannelContext::createFrom($context);
            });

        $orderConverter
            ->expects(static::once())
            ->method('convertToCart')
            ->willReturnCallback(function (OrderEntity $order, Context $context) use ($cart) {
                static::assertSame($order->getTaxStatus(), CartPrice::TAX_STATE_FREE);
                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                return $cart;
            });

        $orderConverter
            ->expects(static::once())
            ->method('convertToOrder')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                $salesChannelContext = $this->createMock(SalesChannelContext::class);
                $salesChannelContext->method('getTaxState')
                    ->willReturn(CartPrice::TAX_STATE_FREE);

                return CartTransformer::transform(
                    $cart,
                    $salesChannelContext,
                    '',
                    $conversionContext->shouldIncludeOrderDate()
                );
            });

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects(static::once())
            ->method('loadByCart')
            ->willReturn(
                new RuleLoaderResult(
                    $cart,
                    new RuleCollection()
                )
            );

        $recalculationService = new RecalculationService(
            $entityRepository,
            $orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->recalculateOrder($orderId, $context);
    }
}
