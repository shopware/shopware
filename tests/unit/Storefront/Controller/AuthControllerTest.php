<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\AuthController
 */
class AuthControllerTest extends TestCase
{
    private AuthControllerTestClass $controller;

    private MockObject&AccountLoginPageLoader $accountLoginPageLoader;

    private MockObject&AbstractSendPasswordRecoveryMailRoute $passwordRecoveryPageLoader;

    private MockObject&AbstractLoginRoute $loginRoute;

    private MockObject&SalesChannelContextServiceInterface $salesChannelContextService;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $this->passwordRecoveryPageLoader = $this->createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $resetPasswordRoute = $this->createMock(AbstractResetPasswordRoute::class);
        $this->loginRoute = $this->createMock(AbstractLoginRoute::class);
        $logoutRoute = $this->createMock(AbstractLogoutRoute::class);
        $cartFacade = $this->createMock(StorefrontCartFacade::class);
        $recoverPasswordRoute = $this->createMock(AccountRecoverPasswordPageLoader::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextServiceInterface::class);

        $this->controller = new AuthControllerTestClass(
            $this->accountLoginPageLoader,
            $this->passwordRecoveryPageLoader,
            $resetPasswordRoute,
            $this->loginRoute,
            $logoutRoute,
            $cartFacade,
            $recoverPasswordRoute,
            $this->salesChannelContextService,
        );

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $this->controller->setContainer($containerBuilder);
    }

    public function testAccountRegister(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.login.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $this->accountLoginPageLoader->expects(static::once())
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

    public function testLoginNewContextIsAdded(): void
    {
        $this->loginRoute
            ->method('login')
            ->willReturn(new ContextTokenResponse('context_token_response'));

        $newSalesChannelContext = Generator::createSalesChannelContext();
        $this->salesChannelContextService
            ->expects(static::once())
            ->method('get')
            ->willReturn($newSalesChannelContext);

        $oldSalesChannelContext = Generator::createSalesChannelContext();
        $oldSalesChannelContext->assign(['customer' => null]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $oldSalesChannelContext);

        $response = $this->controller->login($request, new RequestDataBag(), $oldSalesChannelContext);

        /** @var SalesChannelContext $newSalesChannelContext */
        $newSalesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        static::assertNotSame(
            $oldSalesChannelContext,
            $newSalesChannelContext,
            'Sales Channel context should have been changed after login to update the states in cache'
        );
        static::assertInstanceOf(CustomerEntity::class, $newSalesChannelContext->getCustomer());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGuestLoginPageWithoutRedirectParametersThrows(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Guest account is not allowed to login');

        $this->controller->guestLoginPage($request, $context);
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
            ->expects(static::once())
            ->method('sendRecoveryMail')
            ->willThrowException($exception);

        $this->controller->generateAccountRecovery($request, $dataBag, Generator::createSalesChannelContext());

        static::assertSame('frontend.account.recover.page', $this->controller->forwardToRoute);

        /** @var ConstraintViolationException $formViolations */
        $formViolations = $this->controller->forwardToRouteAttributes['formViolations'];

        static::assertSame('Caught 1 violation errors.', $formViolations->getMessage());
        static::assertSame('This value is not a valid email address.', $formViolations->getViolations()->get(1)->getMessage());
    }
}

/**
 * @internal
 */
class AuthControllerTestClass extends AuthController
{
    use StorefrontControllerMockTrait;
}
