<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class StoreApiMcpCapabilityDiscoveryTest extends TestCase
{
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testStoreApiMcpListsStoreApiToolsOnly(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        $browser = $this->createSalesChannelBrowser();

        $browser->request(
            'POST',
            '/store-api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'store-api-mcp-discovery-test', 'version' => '1.0'],
                ],
                'id' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        $sessionHeaders = $browser->getResponse()->headers->all();
        $sessionHeader = $sessionHeaders['mcp-session-id'] ?? $sessionHeaders['Mcp-Session-Id'] ?? null;
        $sessionId = \is_string($sessionHeader[0] ?? null) ? $sessionHeader[0] : null;

        $browser->request(
            'POST',
            '/store-api/_mcp',
            [],
            [],
            array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ]),
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => new \stdClass(),
                'id' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), 'Store API MCP endpoint returned non-200 status');

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'Store API MCP response was empty');

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('result', $response, 'Store API MCP response missing result: ' . $content);

        $tools = array_column($response['result']['tools'] ?? [], 'name');

        static::assertContains('shopware-store-api-context', $tools);
        static::assertNotContains('shopware-entity-search', $tools);
        static::assertNotContains('shopware-theme-config', $tools);
    }
}
