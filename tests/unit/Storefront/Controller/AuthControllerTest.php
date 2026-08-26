<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractConvertGuestRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Page\Account\Login\AccountGuestLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AuthController::class)]
class AuthControllerTest extends TestCase
{
    private AuthControllerTestClass $controller;

    private AccountLoginPageLoader&Stub $accountLoginPageLoader;

    private AbstractSendPasswordRecoveryMailRoute&Stub $passwordRecoveryPageLoader;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = static::createStub(AccountLoginPageLoader::class);
        $this->passwordRecoveryPageLoader = static::createStub(AbstractSendPasswordRecoveryMailRoute::class);

        $this->controller = $this->createController();
    }

    public function testAccountRegister(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.login.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $accountLoginPageLoader->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);
        $controller = $this->createController($accountLoginPageLoader);

        $controller->loginPage($request, $dataBag, $context);

        static::assertSame($page, $controller->renderStorefrontParameters['page']);
        static::assertSame($dataBag, $controller->renderStorefrontParameters['data']);
        static::assertSame('frontend.account.home.page', $controller->renderStorefrontParameters['redirectTo'] ?? '');
        static::assertSame('[]', $controller->renderStorefrontParameters['redirectParameters'] ?? '');
        static::assertSame('frontend.account.login.page', $controller->renderStorefrontParameters['errorRoute'] ?? '');
        static::assertInstanceOf(AccountLoginPageLoadedHook::class, $controller->calledHook);
    }

    public function testGuestLoginPageWithoutRedirectParametersRedirects(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();

        $this->controller->guestLoginPage($request, $context);

        static::assertArrayHasKey('frontend.account.login.page', $this->controller->redirected);
        static::assertArrayHasKey('danger', $this->controller->flashBag);
        static::assertArrayHasKey(0, $this->controller->flashBag['danger']);
        static::assertSame('account.orderGuestLoginWrongCredentials', $this->controller->flashBag['danger'][0]);
    }

    public function testGuestLoginPageWithoutRedirectParametersRendersEmptyArray(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();
        $request->query->set('redirectTo', 'frontend.account.order.single.page');

        $page = new AccountLoginPage();
        $accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $accountLoginPageLoader->expects($this->once())
            ->method('load')
            ->willReturn($page);
        $controller = $this->createController($accountLoginPageLoader);

        $controller->guestLoginPage($request, $context);

        static::assertSame('@Storefront/storefront/page/account/guest-auth.html.twig', $controller->renderStorefrontView);
        static::assertSame([], $controller->renderStorefrontParameters['redirectParameters'] ?? null);
        static::assertSame('frontend.account.order.single.page', $controller->renderStorefrontParameters['redirectTo'] ?? null);
        static::assertInstanceOf(AccountGuestLoginPageLoadedHook::class, $controller->calledHook);
    }

    public function testGuestLoginPageNormalizesNonArrayRedirectParameters(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();
        $request->query->set('redirectTo', 'frontend.account.order.single.page');
        $request->query->set('redirectParameters', 'not-an-array');

        $this->accountLoginPageLoader->method('load')->willReturn(new AccountLoginPage());

        $this->controller->guestLoginPage($request, $context);

        static::assertSame([], $this->controller->renderStorefrontParameters['redirectParameters'] ?? null);
    }

    public function testGuestLoginPageKeepsArrayRedirectParameters(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();
        $request->query->set('redirectTo', 'frontend.account.order.single.page');
        $request->query->set('redirectParameters', ['deepLinkCode' => 'abc']);

        $this->accountLoginPageLoader->method('load')->willReturn(new AccountLoginPage());

        $this->controller->guestLoginPage($request, $context);

        static::assertSame(['deepLinkCode' => 'abc'], $this->controller->renderStorefrontParameters['redirectParameters'] ?? null);
    }

    public function testGuestCustomerOnLoginPageShouldBeLoggedOut(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getCustomer()?->setGuest(true);

        $this->controller->loginPage(new Request(), new RequestDataBag(), $context);

        static::assertArrayHasKey('frontend.account.logout.page', $this->controller->redirected);
    }

    #[DataProvider('loginRedirectProvider')]
    public function testLoginWithBadCredentialsForwardsToCorrectRoute(?string $redirectTo, string $expectedRoute): void
    {
        $loginRoute = static::createStub(AbstractLoginRoute::class);
        $loginRoute->method('login')->willThrowException(new BadCredentialsException());

        $controller = $this->createController(loginRoute: $loginRoute);

        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();
        if ($redirectTo !== null) {
            $request->request->set('redirectTo', $redirectTo);
        }

        $controller->login($request, new RequestDataBag(), $context);

        static::assertSame($expectedRoute, $controller->forwardToRoute);
        static::assertTrue($controller->forwardToRouteAttributes['loginError']);
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public static function loginRedirectProvider(): array
    {
        return [
            'from checkout' => ['frontend.checkout.confirm.page', 'frontend.checkout.register.page'],
            'unexpected route (wishlist)' => ['frontend.account.wishlist.page', 'frontend.account.login.page'],
            'external url attack' => ['https://www.shopware.com', 'frontend.account.login.page'],
            'empty/null fallback' => [null, 'frontend.account.login.page'],
        ];
    }

    public function testLogoutOptsTheResponseIntoClearSiteData(): void
    {
        $request = new Request();

        $this->controller->logout($request, Generator::generateSalesChannelContext(), new RequestDataBag());

        static::assertTrue($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA));
        static::assertArrayHasKey('frontend.account.login.page', $this->controller->redirected);
    }

    public function testLogoutWithoutCustomerDoesNotOptIntoClearSiteData(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();

        $this->controller->logout($request, $context, new RequestDataBag());

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA));
    }

    public function testGenerateAccountRecoveryThrowsConstraintException(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.recover.page');

        $dataBag = new RequestDataBag();
        $data = new DataBag();
        $data->set('email', 'test@test');
        $dataBag->set('email', $data);

        $validation = new DataValidationDefinition('customer.email.recover');
        $validation->add('email', new Email());

        $dataValidator = new DataValidator(Validation::createValidatorBuilder()->getValidator());

        $violations = $dataValidator->getViolations(['email' => 'test@test'], $validation);

        $exception = new ConstraintViolationException($violations, ['email' => 'test@test']);

        $passwordRecoveryPageLoader = $this->createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $passwordRecoveryPageLoader
            ->expects($this->once())
            ->method('sendRecoveryMail')
            ->willThrowException($exception);
        $controller = $this->createController(passwordRecoveryPageLoader: $passwordRecoveryPageLoader);

        $controller->generateAccountRecovery($request, $dataBag, Generator::generateSalesChannelContext());

        static::assertSame('frontend.account.recover.page', $controller->forwardToRoute);

        /** @var ConstraintViolationException $formViolations */
        $formViolations = $controller->forwardToRouteAttributes['formViolations'];

        static::assertSame('Caught 1 violation errors.', $formViolations->getMessage());
        static::assertSame('This value is not a valid email address.', $formViolations->getViolations()->get(1)->getMessage());
    }

    public function testConvertSetsInfoFlashOnRateLimitExceeded(): void
    {
        $convertGuestRoute = static::createStub(ConvertGuestRoute::class);

        $convertGuestRoute->method('convertGuest')
            ->willThrowException(
                new RateLimitExceededException(60)
            );

        $this->controller = new AuthControllerTestClass(
            static::createStub(AccountLoginPageLoader::class),
            static::createStub(AbstractSendPasswordRecoveryMailRoute::class),
            static::createStub(ResetPasswordRoute::class),
            static::createStub(LoginRoute::class),
            static::createStub(AbstractLogoutRoute::class),
            static::createStub(ImitateCustomerRoute::class),
            static::createStub(StorefrontCartFacade::class),
            static::createStub(AccountRecoverPasswordPageLoader::class),
            $convertGuestRoute,
            static::createStub(SystemConfigService::class),
        );

        $data = new RequestDataBag([
            'password' => 'password',
        ]);

        $customer = new CustomerEntity();
        $customer->setGuest(true);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $this->controller->convert($data, $context);

        static::assertSame('frontend.account.convert.page', $this->controller->forwardToRoute);
    }

    private function createController(
        ?AccountLoginPageLoader $accountLoginPageLoader = null,
        ?AbstractSendPasswordRecoveryMailRoute $passwordRecoveryPageLoader = null,
        ?AbstractLoginRoute $loginRoute = null,
    ): AuthControllerTestClass {
        $resetPasswordRoute = static::createStub(AbstractResetPasswordRoute::class);
        $loginRoute ??= static::createStub(AbstractLoginRoute::class);
        $logoutRoute = static::createStub(AbstractLogoutRoute::class);
        $imitateCustomerRoute = static::createStub(AbstractImitateCustomerRoute::class);
        $cartFacade = static::createStub(StorefrontCartFacade::class);
        $recoverPasswordRoute = static::createStub(AccountRecoverPasswordPageLoader::class);

        $abstractConvertGuestRoute = static::createStub(AbstractConvertGuestRoute::class);
        $systemConfigService = static::createStub(SystemConfigService::class);

        $controller = new AuthControllerTestClass(
            $accountLoginPageLoader ?? $this->accountLoginPageLoader,
            $passwordRecoveryPageLoader ?? $this->passwordRecoveryPageLoader,
            $resetPasswordRoute,
            $loginRoute,
            $logoutRoute,
            $imitateCustomerRoute,
            $cartFacade,
            $recoverPasswordRoute,
            $abstractConvertGuestRoute,
            $systemConfigService
        );

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $controller->setContainer($containerBuilder);

        return $controller;
    }
}

/**
 * @internal
 */
class AuthControllerTestClass extends AuthController implements ResetInterface
{
    use StorefrontControllerMockTrait;
}
