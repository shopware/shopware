<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

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
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\AuthController
 */
class AuthControllerTest extends TestCase
{
    public function testLoginNewContextIsAdded(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());

        $abstractLoginRouteStub = $this->createStub(AbstractLoginRoute::class);
        $abstractLoginRouteStub
            ->method('login')
            ->willReturn(new ContextTokenResponse('context_token_response'));

        $newSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $newSalesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        $salesChannelContextService = $this->createStub(SalesChannelContextServiceInterface::class);
        $salesChannelContextService
            ->method('get')
            ->willReturn($newSalesChannelContext);

        $authController = new AuthController(
            $this->createStub(AccountLoginPageLoader::class),
            $this->createStub(AbstractSendPasswordRecoveryMailRoute::class),
            $this->createStub(AbstractResetPasswordRoute::class),
            $abstractLoginRouteStub,
            $this->createStub(AbstractLogoutRoute::class),
            $this->createStub(StorefrontCartFacade::class),
            $this->createStub(AccountRecoverPasswordPageLoader::class),
            $salesChannelContextService,
        );
        $authController->setContainer($containerBuilder);

        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getLanguageIdChain')
            ->willReturn([null]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $response = $authController->login($request, new RequestDataBag(), $salesChannelContext);

        /** @var SalesChannelContext $newSalesChannelContext */
        $newSalesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        static::assertNotSame(
            $salesChannelContext,
            $newSalesChannelContext,
            'Sales Channel context should have been changed after login to update the states in cache'
        );
        static::assertInstanceOf(CustomerEntity::class, $newSalesChannelContext->getCustomer());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
