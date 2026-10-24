<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpOAuthMetadata;
use Shopware\Core\Framework\Mcp\Controller\McpOAuthMetadataController;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpOAuthMetadataController::class)]
class McpOAuthMetadataControllerTest extends TestCase
{
    public function testProtectedResourceReturnsTheDocumentWithUnescapedSlashes(): void
    {
        $response = $this->createController()->protectedResource(Request::create('https://shop.example/.well-known/oauth-protected-resource/api/_mcp'));

        static::assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        static::assertStringContainsString('"resource":"https://shop.example/api/_mcp"', $content);
        static::assertStringNotContainsString('\/', $content);
    }

    public function testAuthorizationServerReturnsTheIssuer(): void
    {
        $response = $this->createController()->authorizationServer(Request::create('https://shop.example/.well-known/oauth-authorization-server/api'));

        static::assertSame(200, $response->getStatusCode());
        $document = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($document);
        static::assertSame('https://shop.example/api', $document['issuer']);
    }

    private function createController(): McpOAuthMetadataController
    {
        $allowedHostsProvider = static::createStub(McpAllowedHostsProvider::class);
        $allowedHostsProvider->method('getAllowedHosts')->willReturn(['shop.example']);

        return new McpOAuthMetadataController(new McpOAuthMetadata($allowedHostsProvider, 'https://shop.example'));
    }
}
