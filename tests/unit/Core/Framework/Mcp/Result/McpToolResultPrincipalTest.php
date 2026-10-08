<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPrincipal;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultPrincipal::class)]
class McpToolResultPrincipalTest extends TestCase
{
    public function testAnIntegrationAndItsUserNameTheAdminPrincipal(): void
    {
        static::assertSame('admin:integration-id:user-id', McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource('user-id', 'integration-id'))));
        static::assertSame('admin:integration-id:', McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource(null, 'integration-id'))));
        static::assertSame('admin::user-id', McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource('user-id'))));
    }

    public function testTheSalesChannelAndContextTokenNameTheStorePrincipal(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext(token: 'context-token');

        $request = new Request(server: ['HTTP_SW_CONTEXT_TOKEN' => 'context-token']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $salesChannelContext->getContext());

        static::assertSame('store:' . $salesChannelContext->getSalesChannelId() . ':context-token', McpToolResultPrincipal::fromRequest($request));
    }

    public function testAStoreCallWithoutAContextTokenHasNoPrincipal(): void
    {
        // The context resolver puts a random token into the headers of such a request; the client never sees it.
        $salesChannelContext = Generator::generateSalesChannelContext(token: 'minted-for-this-request');

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'minted-for-this-request');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        static::assertNull(McpToolResultPrincipal::fromRequest($request));
    }

    public function testThereIsNoPrincipalWithoutAnAuthenticatedCaller(): void
    {
        static::assertNull(McpToolResultPrincipal::fromRequest(new Request()));
        static::assertNull(McpToolResultPrincipal::fromRequest($this->adminRequest(new SystemSource())));
        static::assertNull(McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource(null))));
    }

    private function adminRequest(AdminApiSource|SystemSource $source): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context($source));

        return $request;
    }
}
