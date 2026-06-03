<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class StoreApiMcpCapabilityDiscoveryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testStoreApiMcpListsStoreApiToolsOnly(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        $tools = $this->listTools();

        static::assertContains('shopware-store-api-context', $tools);
        static::assertNotContains('shopware-entity-search', $tools);
        static::assertNotContains('shopware-theme-config', $tools);
    }

    /**
     * @return list<string>
     */
    private function listTools(): array
    {
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

        $sessionId = $this->extractSessionId($browser->getResponse()->headers->all());

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

        static::assertSame(200, $browser->getResponse()->getStatusCode(), 'Store API MCP endpoint returned non-200 status');

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'Store API MCP response was empty');

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('result', $response, 'Store API MCP response missing result: ' . $content);

        return array_column($response['result']['tools'] ?? [], 'name');
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    private function extractSessionId(array $headers): ?string
    {
        $sessionHeader = $headers['mcp-session-id'] ?? $headers['Mcp-Session-Id'] ?? null;
        $value = $sessionHeader[0] ?? null;

        return \is_string($value) ? $value : null;
    }
}
