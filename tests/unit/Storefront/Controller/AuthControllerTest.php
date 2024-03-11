<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[CoversClass(AuthController::class)]
class AuthControllerTest extends TestCase
{
    private AuthControllerTestClass $controller;

    private MockObject&AccountLoginPageLoader $accountLoginPageLoader;

    private MockObject&AbstractLoginRoute $loginRoute;

    private MockObject&SalesChannelContextServiceInterface $salesChannelContextService;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $passwordRecoveryPageLoader = $this->createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $resetPasswordRoute = $this->createMock(AbstractResetPasswordRoute::class);
        $this->loginRoute = $this->createMock(AbstractLoginRoute::class);
        $logoutRoute = $this->createMock(AbstractLogoutRoute::class);
        $cartFacade = $this->createMock(StorefrontCartFacade::class);
        $recoverPasswordRoute = $this->createMock(AccountRecoverPasswordPageLoader::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextServiceInterface::class);

        $this->controller = new AuthControllerTestClass(
            $this->accountLoginPageLoader,
            $passwordRecoveryPageLoader,
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

    public function testGuestLoginPageWithoutRedirectParametersRedirects(): void
    {
        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $request = new Request();

        $this->controller->guestLoginPage($request, $context);

        static::assertArrayHasKey('frontend.account.login.page', $this->controller->redirected);
        static::assertArrayHasKey('danger', $this->controller->flashBag);
        static::assertArrayHasKey(0, $this->controller->flashBag['danger']);
        static::assertEquals('account.orderGuestLoginWrongCredentials', $this->controller->flashBag['danger'][0]);
    }
}

/**
 * @internal
 */
class AuthControllerTestClass extends AuthController implements ResetInterface
{
    use StorefrontControllerMockTrait;

    /**
     * @var array<string, mixed>
     */
    public array $flashBag = [];

    /**
     * @var array<string, mixed>
     */
    public array $redirected = [];

    public function reset(): void
    {
        $this->flashBag = [];
        $this->redirected = [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $snippet, array $parameters = []): string
    {
        return $snippet;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashBag[$type][] = $message;
    }

    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        $this->redirected[$route][] = [
            'parameters' => $parameters,
            'status' => $status,
        ];

        return new RedirectResponse('/');
    }
}
