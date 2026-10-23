<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\DocumentController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DocumentController::class)]
class DocumentControllerTest extends TestCase
{
    #[DataProvider('documentResponses')]
    public function testAddsTheInjectedPolicyOnlyToSuccessfulFileResponses(Response $response, bool $protected): void
    {
        $response->headers->set('Content-Security-Policy', 'img-src \'self\'');
        $route = static::createStub(AbstractDocumentRoute::class);
        $route->method('download')->willReturn($response);
        $controller = new DocumentController($route, static::createStub(AbstractLogoutRoute::class), 'script-src \'none\'');

        $result = $controller->downloadDocument(
            new Request(attributes: ['deepLinkCode' => 'code', 'fileType' => 'html']),
            static::createStub(SalesChannelContext::class),
            'document-id',
        );

        static::assertSame($response->getStatusCode(), $result->getStatusCode());
        static::assertSame($response->getContent(), $result->getContent());
        static::assertSame(
            $protected ? ['img-src \'self\'', 'script-src \'none\''] : ['img-src \'self\''],
            $result->headers->all('Content-Security-Policy'),
        );
    }

    /**
     * @return iterable<string, array{Response, bool}>
     */
    public static function documentResponses(): iterable
    {
        yield 'inline HTML' => [new Response('<html>Invoice</html>', 200, ['Content-Disposition' => 'inline', 'Content-Type' => 'text/html']), true];
        yield 'attachment' => [new Response('document', 200, ['Content-Disposition' => 'attachment']), true];
        yield 'error' => [new Response('error', 400, ['Content-Disposition' => 'inline']), false];
        yield 'no document' => [new Response('', 204), false];
    }
}
