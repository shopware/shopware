<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartCodeClaimHandler;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionCartCodeClaimHandler::class)]
class PromotionCartCodeClaimHandlerTest extends TestCase
{
    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testClaimReturnsTrueWhenGlobalCodePromotionIsFound(): void
    {
        $gateway = $this->createMock(PromotionGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('get')
            ->willReturn($this->promotionCollectionWith(1));

        $handler = $this->newHandler($gateway, static::createStub(PromotionItemBuilder::class), static::createStub(CartService::class));

        static::assertTrue($handler->claim('SUMMER20', $this->context));
    }

    public function testClaimReturnsTrueWhenOnlyIndividualCodePromotionIsFound(): void
    {
        $gateway = $this->createMock(PromotionGatewayInterface::class);
        $matcher = $this->exactly(2);
        $gateway->expects($matcher)
            ->method('get')
            ->willReturnCallback(fn (Criteria $criteria): PromotionCollection => match ($matcher->numberOfInvocations()) {
                1 => $this->promotionCollectionWith(0),
                2 => $this->promotionCollectionWith(1),
                default => static::fail('too many calls of get'),
            });

        $handler = $this->newHandler($gateway, static::createStub(PromotionItemBuilder::class), static::createStub(CartService::class));

        static::assertTrue($handler->claim('IND-CODE', $this->context));
    }

    public function testClaimReturnsFalseWhenNoPromotionIsFound(): void
    {
        $gateway = $this->createMock(PromotionGatewayInterface::class);
        $gateway->expects($this->exactly(2))
            ->method('get')
            ->willReturn($this->promotionCollectionWith(0));

        $handler = $this->newHandler($gateway, static::createStub(PromotionItemBuilder::class), static::createStub(CartService::class));

        static::assertFalse($handler->claim('UNKNOWN', $this->context));
    }

    public function testHandleAddsPlaceholderLineItemToCart(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $resultCart = new Cart(Uuid::randomHex());
        $lineItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);

        $itemBuilder = $this->createMock(PromotionItemBuilder::class);
        $itemBuilder->expects($this->once())
            ->method('buildPlaceholderItem')
            ->with('SUMMER20')
            ->willReturn($lineItem);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('add')
            ->with($cart, $lineItem, $this->context)
            ->willReturn($resultCart);

        $handler = $this->newHandler(static::createStub(PromotionGatewayInterface::class), $itemBuilder, $cartService);

        static::assertSame($resultCart, $handler->handle('SUMMER20', $cart, $this->context));
    }

    public function testGetSuccessMessageReturnsNull(): void
    {
        $handler = $this->newHandler(
            static::createStub(PromotionGatewayInterface::class),
            static::createStub(PromotionItemBuilder::class),
            static::createStub(CartService::class)
        );

        static::assertNull($handler->getSuccessMessage());
    }

    private function newHandler(PromotionGatewayInterface $gateway, PromotionItemBuilder $itemBuilder, CartService $cartService): PromotionCartCodeClaimHandler
    {
        return new PromotionCartCodeClaimHandler($gateway, $itemBuilder, $cartService);
    }

    private function promotionCollectionWith(int $count): PromotionCollection
    {
        $elements = [];
        for ($i = 0; $i < $count; ++$i) {
            $promotion = new PromotionEntity();
            $promotion->setId(Uuid::randomHex());
            $elements[$promotion->getId()] = $promotion;
        }

        return new PromotionCollection($elements);
    }
}
