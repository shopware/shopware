<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Register\AccountRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPage;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\RegisterController
 */
class RegisterControllerTest extends TestCase
{
    private RegisterControllerTestClass $controller;

    private MockObject&AccountLoginPageLoader $accountLoginPageLoader;

    private MockObject&CheckoutRegisterPageLoader $checkoutRegisterPageLoader;

    private MockObject&CartService $cartService;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerConfirmRoute = $this->createMock(RegisterConfirmRoute::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->checkoutRegisterPageLoader = $this->createMock(CheckoutRegisterPageLoader::class);
        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerGroupRegistrationPageLoader = $this->createMock(CustomerGroupRegistrationPageLoader::class);
        $domainRepository = $this->createMock(EntityRepository::class);

        $this->controller = new RegisterControllerTestClass(
            $this->accountLoginPageLoader,
            $registerRoute,
            $registerConfirmRoute,
            $this->cartService,
            $this->checkoutRegisterPageLoader,
            $systemConfigServiceMock,
            $customerRepository,
            $customerGroupRegistrationPageLoader,
            $domainRepository,
        );
    }

    public function testAccountRegister(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.register.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $this->accountLoginPageLoader->expects(static::once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $this->controller->accountRegisterPage($request, $dataBag, $context);

        static::assertSame($page, $this->controller->renderStorefrontParameters['page']);
        static::assertSame($dataBag, $this->controller->renderStorefrontParameters['data']);
        static::assertSame('frontend.account.home.page', $this->controller->renderStorefrontParameters['redirectTo'] ?? '');
        static::assertSame('[]', $this->controller->renderStorefrontParameters['redirectParameters'] ?? '');
        static::assertSame('frontend.account.register.page', $this->controller->renderStorefrontParameters['errorRoute'] ?? '');
        static::assertInstanceOf(AccountRegisterPageLoadedHook::class, $this->controller->calledHook);
    }

    public function testCheckoutRegister(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.checkout.register.page');
        $dataBag = new RequestDataBag();
        $page = new CheckoutRegisterPage();
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem('test', 'test'));

        $this->checkoutRegisterPageLoader->expects(static::once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $this->cartService->expects(static::once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $this->controller->checkoutRegisterPage($request, $dataBag, $context);

        static::assertSame($page, $this->controller->renderStorefrontParameters['page']);
        static::assertSame($dataBag, $this->controller->renderStorefrontParameters['data']);
        static::assertSame('frontend.checkout.confirm.page', $this->controller->renderStorefrontParameters['redirectTo'] ?? '');
        static::assertSame('frontend.checkout.register.page', $this->controller->renderStorefrontParameters['errorRoute'] ?? '');
        static::assertInstanceOf(CheckoutRegisterPageLoadedHook::class, $this->controller->calledHook);
    }
}

/**
 * @internal
 */
class RegisterControllerTestClass extends RegisterController
{
    use StorefrontControllerMockTrait;
}
