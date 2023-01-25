<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CartLineItemController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\CartLineItemController
 */
class CartLineItemControllerTest extends TestCase
{
    private CartLineItemController $controller;

    private LineItemFactoryRegistry&MockObject $lineItemFactory;

    private CartService&MockObject $cartService;

    private ContainerInterface&MockObject $container;

    public function setUp(): void
    {
        $this->lineItemFactory = $this->createMock(LineItemFactoryRegistry::class);
        $this->cartService = $this->createMock(CartService::class);

        $this->controller = new CartLineItemController(
            $this->cartService,
            $this->createMock(PromotionItemBuilder::class),
            $this->createMock(ProductLineItemFactory::class),
            $this->createMock(HtmlSanitizer::class),
            $this->createMock(AbstractProductListRoute::class),
            $this->lineItemFactory,
        );

        $this->container = $this->createMock(ContainerInterface::class);

        $this->controller->setContainer($this->container);
    }

    public function testAddLineItemsCallsLineItemFactory(): void
    {
        $productId = Uuid::randomHex();
        $lineItemData = [
            'id' => $productId,
            'referencedId' => $productId,
            'type' => 'product',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
        ];

        $request = new Request([], ['lineItems' => [$productId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = $this->createMock(SalesChannelContext::class);
        $expectedLineItem = new LineItem($productId, 'product');

        $this->lineItemFactory->expects(static::once())
            ->method('create')
            ->with($lineItemData, $this->createMock(SalesChannelContext::class))
            ->willReturn($expectedLineItem);

        $this->cartService->expects(static::once())
            ->method('add')
            ->with($cart, [$expectedLineItem], $context)
            ->willReturn($cart);

        $stack = $this->createMock(RequestStack::class);
        $stack->method('getSession')->willReturn(new Session(new MockArraySessionStorage()));
        $this->container->method('get')
            ->withConsecutive(['translator'], ['request_stack'])
            ->willReturnOnConsecutiveCalls($this->createMock(TranslatorInterface::class), $stack);

        $this->controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsWithoutExistingFactory(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $productId = Uuid::randomHex();
        $lineItemData = [
            'id' => $productId,
            'referencedId' => $productId,
            'type' => 'nonexistenttype',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
        ];

        $request = new Request([], ['lineItems' => [$productId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = $this->createMock(SalesChannelContext::class);
        $expectedLineItem = new LineItem($productId, 'nonexistenttype');

        $this->lineItemFactory->expects(static::once())
            ->method('create')
            ->with($lineItemData, $this->createMock(SalesChannelContext::class))
            ->willThrowException(CartException::lineItemTypeNotSupported('nonexistenttype'));

        $this->cartService->expects(static::once())
            ->method('add')
            ->with($cart, [$expectedLineItem], $context)
            ->willReturn($cart);

        $stack = $this->createMock(RequestStack::class);
        $stack->method('getSession')->willReturn(new Session(new MockArraySessionStorage()));
        $this->container->method('get')
            ->withConsecutive(['translator'], ['request_stack'])
            ->willReturnOnConsecutiveCalls($this->createMock(TranslatorInterface::class), $stack);

        $this->controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsCartExceptionWillBeThrown(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $productId = Uuid::randomHex();
        $lineItemData = [
            'id' => $productId,
            'referencedId' => $productId,
            'type' => 'nonexistenttype',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
        ];

        $request = new Request([], ['lineItems' => [$productId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = $this->createMock(SalesChannelContext::class);

        $exception = CartException::invalidPriceDefinition();
        $this->lineItemFactory->expects(static::once())
            ->method('create')
            ->with($lineItemData, $this->createMock(SalesChannelContext::class))
            ->willThrowException($exception);

        $this->cartService->expects(static::never())->method('add');

        $this->expectExceptionObject($exception);
        $this->controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }
}
