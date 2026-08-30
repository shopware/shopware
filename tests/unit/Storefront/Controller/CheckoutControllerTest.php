<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutController::class)]
class CheckoutControllerTest extends TestCase
{
    private CheckoutControllerTestClass $controller;

    private CartService&Stub $cartServiceMock;

    private CheckoutCartPageLoader&Stub $cartPageLoaderMock;

    private CheckoutConfirmPageLoader&Stub $confirmPageLoaderMock;

    private CheckoutFinishPageLoader&Stub $finishPageLoaderMock;

    private OrderService&Stub $orderServiceMock;

    private PaymentProcessor&Stub $paymentProcessorMock;

    private OffcanvasCartPageLoader&Stub $offcanvasCartPageLoaderMock;

    private AbstractLogoutRoute&Stub $logoutRouteMock;

    private AbstractCartLoadRoute&Stub $cartLoadRouteMock;

    protected function setUp(): void
    {
        $this->cartServiceMock = static::createStub(CartService::class);
        $this->cartPageLoaderMock = static::createStub(CheckoutCartPageLoader::class);
        $this->confirmPageLoaderMock = static::createStub(CheckoutConfirmPageLoader::class);
        $this->finishPageLoaderMock = static::createStub(CheckoutFinishPageLoader::class);
        $this->orderServiceMock = static::createStub(OrderService::class);
        $this->paymentProcessorMock = static::createStub(PaymentProcessor::class);
        $this->offcanvasCartPageLoaderMock = static::createStub(OffcanvasCartPageLoader::class);
        $this->logoutRouteMock = static::createStub(AbstractLogoutRoute::class);
        $this->cartLoadRouteMock = static::createStub(AbstractCartLoadRoute::class);

        $this->controller = $this->buildController();
    }

    public function testGetCart(): void
    {
        $cart = new CheckoutCartPage();
        $cart->setCart(new Cart(Uuid::randomHex()));
        $this->cartPageLoaderMock->method('load')->willReturn(
            $cart
        );

        $response = $this->controller->cartPage(new Request(), static::createStub(SalesChannelContext::class));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testGetCartRedirectNotOnNoErrors(): void
    {
        $cart = new CheckoutCartPage();
        $cart->setCart(new Cart(Uuid::randomHex()));
        $this->cartPageLoaderMock->method('load')->willReturn(
            $cart
        );

        $request = new Request();
        $request->query->set('redirected', true);

        $response = $this->controller->cartPage($request, static::createStub(SalesChannelContext::class));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testGetCartRedirectOnShippingErrors(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->addErrors(new ShippingMethodChangedError(
            oldShippingMethodId: Uuid::randomHex(),
            oldShippingMethodName: 'old',
            newShippingMethodId: Uuid::randomHex(),
            newShippingMethodName: 'new',
            reason: 'reason',
        ));

        $cartPage = new CheckoutCartPage();
        $cartPage->setCart($cart);

        $this->cartPageLoaderMock->method('load')->willReturn(
            $cartPage
        );

        $request = new Request();
        $request->query->set('redirected', false);

        $response = $this->controller->cartPage($request, static::createStub(SalesChannelContext::class));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.cart.page', $response->getTargetUrl());
    }

    public function testGetCartRedirectOnShippingErrorsPreventLoop(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->addErrors(new ShippingMethodChangedError(
            oldShippingMethodId: Uuid::randomHex(),
            oldShippingMethodName: 'old',
            newShippingMethodId: Uuid::randomHex(),
            newShippingMethodName: 'new',
            reason: 'reason',
        ));

        $cartPage = new CheckoutCartPage();
        $cartPage->setCart($cart);

        $this->cartPageLoaderMock->method('load')->willReturn(
            $cartPage
        );

        $request = new Request();
        $request->query->set('redirected', true);

        $response = $this->controller->cartPage($request, static::createStub(SalesChannelContext::class));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testGetCartJson(): void
    {
        $cart = new Cart(Uuid::randomHex());

        $this->cartLoadRouteMock->method('load')->willReturn(
            new CartResponse($cart)
        );

        $response = $this->controller->cartJson(new Request(), static::createStub(SalesChannelContext::class), $cart);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(CartResponse::class, $response);
        static::assertSame($cart, $response->getObject());
    }

    public function testConfirmPageNoCustomer(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $response = $this->controller->confirmPage(new Request(), $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.register.page', $response->getTargetUrl());
    }

    public function testConfirmPageEmptyCart(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $response = $this->controller->confirmPage(new Request(), $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.cart.page', $response->getTargetUrl());
    }

    public function testConfirmPageWithCart(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $this->cartServiceMock->method('getCart')->willReturn($cart);

        $response = $this->controller->confirmPage(new Request(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testConfirmPageRedirectNotOnNoErrors(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $this->cartServiceMock->method('getCart')->willReturn($cart);

        $request = new Request();
        $request->query->set('redirected', false);

        $response = $this->controller->confirmPage($request, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testConfirmPageRedirectOnShippingErrors(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));
        $cart->addErrors(new ShippingMethodChangedError(
            oldShippingMethodId: Uuid::randomHex(),
            oldShippingMethodName: 'old',
            newShippingMethodId: Uuid::randomHex(),
            newShippingMethodName: 'new',
            reason: 'reason',
        ));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $this->cartServiceMock->method('getCart')->willReturn($cart);

        $cartPage = new CheckoutConfirmPage();
        $cartPage->setCart($cart);

        $this->confirmPageLoaderMock->method('load')->willReturn($cartPage);

        $request = new Request();
        $request->query->set('redirected', false);

        $response = $this->controller->confirmPage($request, $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.confirm.page', $response->getTargetUrl());
    }

    public function testConfirmPageRedirectOnShippingErrorsPreventLoop(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $this->cartServiceMock->method('getCart')->willReturn($cart);

        $cartPage = new CheckoutConfirmPage();
        $cartPage->setCart($cart);

        $this->confirmPageLoaderMock->method('load')->willReturn($cartPage);

        $request = new Request();
        $request->query->set('redirected', true);

        $response = $this->controller->confirmPage($request, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testFinishPageNoCustomer(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $response = $this->controller->finishPage(new Request(), $context, new RequestDataBag());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.register.page', $response->getTargetUrl());
    }

    public function testFinishPageOrderNotFound(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $this->finishPageLoaderMock->method('load')->willThrowException(OrderException::orderNotFound('not-found'));

        $response = $this->controller->finishPage(new Request(), $context, new RequestDataBag());

        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_NOT_FOUND']], $this->controller->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.cart.page', $response->getTargetUrl());
    }

    public function testFinishPagePaymentFailed(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $page = new CheckoutFinishPage();
        $page->setPaymentFailed(true);

        $this->finishPageLoaderMock->method('load')->willReturn($page);

        $response = $this->controller->finishPage(new Request(), $context, new RequestDataBag());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.edit-order.page', $response->headers->get('Location'));
    }

    public function testFinishPageGuestLogout(): void
    {
        $context = Generator::generateSalesChannelContext();

        $page = new CheckoutFinishPage();
        $page->setPaymentFailed(false);
        $page->setLogoutCustomer(true);

        $this->finishPageLoaderMock->method('load')->willReturn($page);

        $logoutRoute = $this->createMock(AbstractLogoutRoute::class);
        $logoutRoute->expects($this->once())->method('logout');

        $controller = $this->buildController(logoutRoute: $logoutRoute);

        $response = $controller->finishPage(new Request(), $context, new RequestDataBag());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testFinishPageNoGuestLogout(): void
    {
        $context = Generator::generateSalesChannelContext();

        $page = new CheckoutFinishPage();
        $page->setPaymentFailed(false);
        $page->setLogoutCustomer(false);

        $this->finishPageLoaderMock->method('load')->willReturn($page);

        $logoutRoute = $this->createMock(AbstractLogoutRoute::class);
        $logoutRoute->expects($this->never())->method('logout');

        $controller = $this->buildController(logoutRoute: $logoutRoute);

        $response = $controller->finishPage(new Request(), $context, new RequestDataBag());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testOrderNoCustomer(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $response = $this->controller->order(new RequestDataBag(), $context, new Request());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.checkout.register.page', $response->getTargetUrl());
    }

    public function testOrder(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())->method('createOrder');

        $controller = $this->buildController(orderService: $orderService);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('url:frontend.checkout.finish.page', $response->getTargetUrl());
    }

    public function testOrderConstraintViolation(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())->method('createOrder')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );

        $controller = $this->buildController(orderService: $orderService);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.confirm.page', $response->getContent());
    }

    public function testOrderCartException(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())->method('createOrder')->willThrowException(
            CartException::invalidCart(
                new ErrorCollection(
                    [
                        new GenericCartError(
                            Uuid::randomHex(),
                            'message',
                            [],
                            1,
                            true,
                            false,
                            true,
                        ),
                    ]
                )
            )
        );

        $controller = $this->buildController(orderService: $orderService);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.confirm.page', $response->getContent());
    }

    public function testOrderCartPaymentException(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())->method('createOrder')->willThrowException(
            PaymentException::unknownPaymentMethodById(Uuid::randomHex())
        );

        $controller = $this->buildController(orderService: $orderService);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.confirm.page', $response->getContent());
        static::assertSame(['danger' => ['error.CHECKOUT__UNKNOWN_PAYMENT_METHOD']], $controller->flashBag);
    }

    public function testOrderCartInvalidOrderException(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())->method('createOrder')->willThrowException(
            CartException::invalidPaymentButOrderStored(Uuid::randomHex())
        );

        $controller = $this->buildController(orderService: $orderService);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.finish.page', $response->getContent());
    }

    public function testOrderPaymentServiceException(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $paymentProcessor = $this->createMock(PaymentProcessor::class);
        $paymentProcessor->expects($this->once())->method('pay')->willThrowException(
            PaymentException::syncProcessInterrupted(Uuid::randomHex(), 'error')
        );

        $controller = $this->buildController(paymentProcessor: $paymentProcessor);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.finish.page', $response->getContent());
    }

    public function testOrderTransitionException(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $paymentProcessor = $this->createMock(PaymentProcessor::class);
        $paymentProcessor->expects($this->once())->method('pay')->willThrowException(
            new IllegalTransitionException('open', 'done', ['in_progress', 'canceled'])
        );

        $controller = $this->buildController(paymentProcessor: $paymentProcessor);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.finish.page', $response->getContent());
    }

    public function testOrderFlowException(): void
    {
        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $paymentProcessor = $this->createMock(PaymentProcessor::class);
        $paymentProcessor->expects($this->once())->method('pay')->willThrowException(
            FlowException::transactionFailed(new IllegalTransitionException('open', 'done', ['in_progress', 'canceled']))
        );

        $controller = $this->buildController(paymentProcessor: $paymentProcessor);

        $response = $controller->order(new RequestDataBag(), $context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.checkout.finish.page', $response->getContent());
    }

    public function testInfo(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));

        $this->cartServiceMock->method('getCart')->willReturn($cart);

        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $response = $this->controller->info($request, $context);

        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertInstanceOf(OffcanvasCartPage::class, $this->controller->renderStorefrontParameters['page']);
    }

    public function testInfoEmptyCart(): void
    {
        $cart = new Cart(Uuid::randomHex());

        $this->cartServiceMock->method('getCart')->willReturn($cart);

        $request = new Request();
        $request->setSession(static::createStub(Session::class));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $response = $this->controller->info($request, $context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testOffCanvas(): void
    {
        $request = new Request();

        $context = static::createStub(SalesChannelContext::class);

        $response = $this->controller->offcanvas($request, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testOffCanvasRedirectOnShippingErrors(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));
        $cart->addErrors(new ShippingMethodChangedError(
            oldShippingMethodId: Uuid::randomHex(),
            oldShippingMethodName: 'old',
            newShippingMethodId: Uuid::randomHex(),
            newShippingMethodName: 'new',
            reason: 'reason',
        ));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $cartPage = new OffcanvasCartPage();
        $cartPage->setCart($cart);

        $this->offcanvasCartPageLoaderMock->method('load')->willReturn($cartPage);

        $request = new Request();
        $request->query->set('redirected', false);

        $response = $this->controller->offcanvas($request, $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.cart.offcanvas', $response->getTargetUrl());
    }

    public function testOffCanvasRedirectOnShippingErrorsPreventLoop(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE));
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $cartPage = new OffcanvasCartPage();
        $cartPage->setCart($cart);

        $this->offcanvasCartPageLoaderMock->method('load')->willReturn($cartPage);

        $request = new Request();
        $request->query->set('redirected', true);

        $response = $this->controller->offcanvas($request, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    private function buildController(
        ?OrderService $orderService = null,
        ?PaymentProcessor $paymentProcessor = null,
        ?AbstractLogoutRoute $logoutRoute = null,
    ): CheckoutControllerTestClass {
        return new CheckoutControllerTestClass(
            $this->cartServiceMock,
            $this->cartPageLoaderMock,
            $this->confirmPageLoaderMock,
            $this->finishPageLoaderMock,
            $orderService ?? $this->orderServiceMock,
            $paymentProcessor ?? $this->paymentProcessorMock,
            $this->offcanvasCartPageLoaderMock,
            $logoutRoute ?? $this->logoutRouteMock,
            $this->cartLoadRouteMock,
            static::createStub(HeaderPageletLoaderInterface::class),
            static::createStub(FooterPageletLoaderInterface::class),
        );
    }
}

/**
 * @internal
 */
class CheckoutControllerTestClass extends CheckoutController
{
    use StorefrontControllerMockTrait;
}
