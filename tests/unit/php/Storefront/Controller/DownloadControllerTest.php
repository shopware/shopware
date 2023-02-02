<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\DownloadController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\DownloadController
 */
class DownloadControllerTest extends TestCase
{
    private MockObject&DownloadRoute $downloadRouteMock;

    private DownloadController $controller;

    public function setUp(): void
    {
        $this->downloadRouteMock = $this->createMock(DownloadRoute::class);

        $this->controller = new DownloadController(
            $this->downloadRouteMock
        );
    }

    public function testResponseReturn(): void
    {
        $this->downloadRouteMock->method('load')->willReturn(new Response());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $response = $this->controller->downloadFile(new Request(), $salesChannelContext);

        static::assertInstanceOf(Response::class, $response);
    }
}
