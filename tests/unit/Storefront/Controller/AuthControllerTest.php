<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPage;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[CoversClass(AuthController::class)]
class AuthControllerTest extends TestCase
{
    private AuthControllerTestClass $controller;

    private MockObject&AccountLoginPageLoader $accountLoginPageLoader;

    private MockObject&AbstractSendPasswordRecoveryMailRoute $passwordRecoveryPageLoader;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $this->passwordRecoveryPageLoader = $this->createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $resetPasswordRoute = $this->createMock(AbstractResetPasswordRoute::class);
        $loginRoute = $this->createMock(AbstractLoginRoute::class);
        $logoutRoute = $this->createMock(AbstractLogoutRoute::class);
        $imitateCustomerRoute = $this->createMock(AbstractImitateCustomerRoute::class);
        $cartFacade = $this->createMock(StorefrontCartFacade::class);
        $recoverPasswordRoute = $this->createMock(AccountRecoverPasswordPageLoader::class);

        $this->controller = new AuthControllerTestClass(
            $this->accountLoginPageLoader,
            $this->passwordRecoveryPageLoader,
            $resetPasswordRoute,
            $loginRoute,
            $logoutRoute,
            $imitateCustomerRoute,
            $cartFacade,
            $recoverPasswordRoute,
        );

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $this->controller->setContainer($containerBuilder);
    }

    public function testAccountRegister(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.login.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $this->accountLoginPageLoader->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $this->controller->loginPage($request, $dataBag, $context);

        static::assertSame($page, $this->controller->renderStorefrontParameters['page']);
        static::assertSame($dataBag, $this->controller->renderStorefrontParameters['data']);
        static::assertSame('frontend.account.home.page', $this->controller->renderStorefrontParameters['redirectTo'] ?? '');
        static::assertSame('[]', $this->controller->renderStorefrontParameters['redirectParameters'] ?? '');
        static::assertSame('frontend.account.login.page', $this->controller->renderStorefrontParameters['errorRoute'] ?? '');
        static::assertInstanceOf(AccountLoginPageLoadedHook::class, $this->controller->calledHook);
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
        static::assertEquals('account.orderGuestLoginWrongCredentials', $this->controller->flashBag['danger'][0]);
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

        $this->passwordRecoveryPageLoader
            ->expects($this->once())
            ->method('sendRecoveryMail')
            ->willThrowException($exception);

        $this->controller->generateAccountRecovery($request, $dataBag, Generator::generateSalesChannelContext());

        static::assertSame('frontend.account.recover.page', $this->controller->forwardToRoute);

        /** @var ConstraintViolationException $formViolations */
        $formViolations = $this->controller->forwardToRouteAttributes['formViolations'];

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

        $this->controller = new AuthControllerStub(
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

        static::assertSame('frontend.account.convert.page', $this->controller->recorder()->forwardToRoute);
    }

    public function testResetPasswordFormRendersFormViolationsForwardedFromResetPassword(): void
    {
        $page = new AccountRecoverPasswordPage();
        $page->setHash('recovery-hash');
        $page->setHashExpired(false);

        $recoverPasswordPageLoader = $this->createMock(AccountRecoverPasswordPageLoader::class);
        $recoverPasswordPageLoader->expects($this->once())
            ->method('load')
            ->with(static::isInstanceOf(Request::class), static::isInstanceOf(SalesChannelContext::class), 'recovery-hash')
            ->willReturn($page);
        $controller = $this->createController(recoverPasswordRoute: $recoverPasswordPageLoader);

        $formViolations = new ConstraintViolationException(new ConstraintViolationList(), []);

        // resetPassword() forwards here with the hash as request attribute
        $request = new Request(attributes: ['hash' => 'recovery-hash', 'formViolations' => $formViolations]);

        $controller->resetPasswordForm($request, Generator::generateSalesChannelContext());

        static::assertSame([], $controller->recorder()->flashBag);
        static::assertSame($page, $controller->recorder()->renderStorefrontParameters['page']);
        static::assertSame($formViolations, $controller->recorder()->renderStorefrontParameters['formViolations']);
    }

    private function createController(
        ?AccountLoginPageLoader $accountLoginPageLoader = null,
        ?AbstractSendPasswordRecoveryMailRoute $passwordRecoveryPageLoader = null,
        ?AbstractLoginRoute $loginRoute = null,
        ?AccountRecoverPasswordPageLoader $recoverPasswordRoute = null,
    ): AuthControllerStub {
        $resetPasswordRoute = static::createStub(AbstractResetPasswordRoute::class);
        $loginRoute ??= static::createStub(AbstractLoginRoute::class);
        $logoutRoute = static::createStub(AbstractLogoutRoute::class);
        $imitateCustomerRoute = static::createStub(AbstractImitateCustomerRoute::class);
        $cartFacade = static::createStub(StorefrontCartFacade::class);
        $recoverPasswordRoute ??= static::createStub(AccountRecoverPasswordPageLoader::class);

        $abstractConvertGuestRoute = static::createStub(AbstractConvertGuestRoute::class);
        $systemConfigService = static::createStub(SystemConfigService::class);

        $controller = new AuthControllerStub(
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
