<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Document\AbstractProductDocumentDownloadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ProductDocumentController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentController::class)]
class ProductDocumentControllerTest extends TestCase
{
    public function testDownloadDelegatesToProductDocumentDownloadRoute(): void
    {
        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $response = new Response('download');

        $route = $this->createMock(AbstractProductDocumentDownloadRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with('product-id', 'document-id', $request, $context)
            ->willReturn($response);

        $controller = new ProductDocumentController($route);

        static::assertSame($response, $controller->download('product-id', 'document-id', $request, $context));
    }
}
