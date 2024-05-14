<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\DownloadController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(DownloadController::class)]
class DownloadControllerTest extends TestCase
{
    private MockObject&DownloadRoute $downloadRouteMock;

    private DownloadController $controller;

    protected function setUp(): void
    {
        $this->downloadRouteMock = $this->createMock(DownloadRoute::class);

        $this->controller = new DownloadController(
            $this->downloadRouteMock
        );
    }

    public function testLoggedOutResponseReturn(): void
    {
        $containerBuilder = new ContainerBuilder();
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(static::once())
            ->method('generate')
            ->with(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => 'foo',
                ]
            )
            ->willReturn('bar');
        $containerBuilder->set('router', $router);
        $containerBuilder->set('event_dispatcher', static::createMock(EventDispatcherInterface::class));
        $this->controller->setContainer($containerBuilder);
        $this->downloadRouteMock->method('load')->willReturn(new Response());

        $request = new Request();
        $request->query->set('deepLinkCode', 'foo');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $response = $this->controller->downloadFile($request, $salesChannelContext);

        static::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testLoggedInResponseReturn(): void
    {
        $this->downloadRouteMock->method('load')->willReturn(new Response());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::once())->method('getCustomer')->willReturn(new CustomerEntity());
        $response = $this->controller->downloadFile(new Request(), $salesChannelContext);

        static::assertInstanceOf(Response::class, $response);
    }
}
