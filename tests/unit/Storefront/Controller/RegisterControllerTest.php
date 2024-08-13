<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPage;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoadedHook;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Register\AccountRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPage;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

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

    private MockObject&CustomerGroupRegistrationPageLoader $customerGroupRegistrationPageLoader;

    private MockObject&RegisterRoute $registerRoute;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $this->registerRoute = $this->createMock(RegisterRoute::class);
        $registerConfirmRoute = $this->createMock(RegisterConfirmRoute::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->checkoutRegisterPageLoader = $this->createMock(CheckoutRegisterPageLoader::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $customerRepository = $this->createMock(EntityRepository::class);
        $this->customerGroupRegistrationPageLoader = $this->createMock(CustomerGroupRegistrationPageLoader::class);
        $domainRepository = $this->createMock(EntityRepository::class);

        $this->controller = new RegisterControllerTestClass(
            $this->accountLoginPageLoader,
            $this->registerRoute,
            $registerConfirmRoute,
            $this->cartService,
            $this->checkoutRegisterPageLoader,
            $this->systemConfigService,
            $customerRepository,
            $this->customerGroupRegistrationPageLoader,
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

    public function testCustomerGroupRegistration(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.customer-group-registration.page');
        $dataBag = new RequestDataBag();
        $page = new CustomerGroupRegistrationPage();
        $page->setGroup(new CustomerGroupEntity());
        $customerGroupId = Uuid::randomHex();

        $this->customerGroupRegistrationPageLoader->expects(static::once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $this->controller->customerGroupRegistration($customerGroupId, $request, $dataBag, $context);

        static::assertSame($page, $this->controller->renderStorefrontParameters['page']);
        static::assertSame($dataBag, $this->controller->renderStorefrontParameters['data']);
        static::assertSame('frontend.account.home.page', $this->controller->renderStorefrontParameters['redirectTo'] ?? '');
        static::assertSame('frontend.account.customer-group-registration.page', $this->controller->renderStorefrontParameters['errorRoute'] ?? '');
        static::assertSame(json_encode(['customerGroupId' => $customerGroupId]), $this->controller->renderStorefrontParameters['errorParameters'] ?? '');
        static::assertInstanceOf(CustomerGroupRegistrationPageLoadedHook::class, $this->controller->calledHook);
    }

    public function testRegisterSuccess(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();

        $response = $this->controller->register($request, $dataBag, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithValueConfirmation(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();
        $dataBag->set('email', 'foo@bar.de');
        $dataBag->set('password', 'password');
        $dataBag->set('createCustomerAccount', true);

        $this->systemConfigService->set('core.loginRegistration.requireEmailConfirmation', true, $context->getSalesChannelId());
        $this->systemConfigService->set('core.loginRegistration.requirePasswordConfirmation', true, $context->getSalesChannelId());

        $expectedDefinition = new DataValidationDefinition('storefront.confirmation');
        $expectedDefinition->add('emailConfirmation', new NotBlank(), new EqualTo(['value' => 'foo@bar.de']));
        $expectedDefinition->add('passwordConfirmation', new NotBlank(), new EqualTo(['value' => 'password']));
        $this->registerRoute
            ->expects(static::once())
            ->method('register')
            ->with($dataBag, $context, false, $expectedDefinition);

        $response = $this->controller->register($request, $dataBag, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithNoErrorRouteParam(): void
    {
        static::expectExceptionMessage('Parameter "errorRoute" is missing.');

        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();

        $this->registerRoute->expects(static::once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $this->controller->register($request, $dataBag, $context);
    }

    public function testRegisterWithErrorRouteParamEmpty(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = $this->createRegisterRequest();
        $request->request->set('errorRoute', '');

        $dataBag = new RequestDataBag();

        $this->registerRoute->expects(static::once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->register($request, $dataBag, $context);

        static::assertSame('frontend.account.register.page', $request->request->get('errorRoute'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithViolation(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = $this->createRegisterRequest();
        $request->request->set('errorRoute', 'some-url');

        $dataBag = new RequestDataBag();

        $this->registerRoute->expects(static::once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->register($request, $dataBag, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function createRegisterRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, $_SERVER['APP_URL']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}

/**
 * @internal
 */
class RegisterControllerTestClass extends RegisterController
{
    use StorefrontControllerMockTrait;
}
