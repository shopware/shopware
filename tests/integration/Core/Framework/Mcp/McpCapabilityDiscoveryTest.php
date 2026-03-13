<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * End-to-end capability discovery test.
 *
 * Calls the live MCP HTTP endpoint (/api/_mcp) using the JSON-RPC protocol
 * and asserts that every expected tool, prompt, and resource name is present
 * in the server's response.
 *
 * This validates the full discovery stack:
 *   DI registration → mcp.tool tag → mcp.yaml scan_dirs → #[McpTool] attribute
 *
 * Unit tests that only load mcp.php do not catch scan_dirs problems (the gap
 * that caused shopware-theme-config to silently disappear). This test does.
 *
 * @internal
 */
#[Package('framework')]
class McpCapabilityDiscoveryTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    public function testAllExpectedToolsAreDiscovered(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        $registered = $this->listCapabilities('tools/list', 'tools');

        foreach (self::expectedTools() as $name) {
            static::assertContains(
                $name,
                $registered,
                \sprintf(
                    'Tool "%s" is missing from tools/list. Check mcp.yaml scan_dirs and mcp.tool DI tag.',
                    $name,
                ),
            );
        }
    }

    public function testAllExpectedPromptsAreDiscovered(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        $registered = $this->listCapabilities('prompts/list', 'prompts');

        foreach (self::expectedPrompts() as $name) {
            static::assertContains(
                $name,
                $registered,
                \sprintf('Prompt "%s" is missing from prompts/list.', $name),
            );
        }
    }

    public function testAllExpectedResourcesAreDiscovered(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        $registered = $this->listCapabilities('resources/list', 'resources');

        foreach (self::expectedResources() as $name) {
            static::assertContains(
                $name,
                $registered,
                \sprintf('Resource "%s" is missing from resources/list.', $name),
            );
        }
    }

    /**
     * All tool names that must be present in tools/list.
     *
     * @return list<string>
     */
    private static function expectedTools(): array
    {
        return [
            'shopware-entity-schema',
            'shopware-entity-search',
            'shopware-entity-aggregate',
            'shopware-entity-read',
            'shopware-entity-upsert',
            'shopware-entity-delete',
            'shopware-system-config-read',
            'shopware-system-config-write',
            'shopware-state-machine-transition',
            'shopware-storefront-search',
            'shopware-order-summary',
            'shopware-customer-lookup',
            'shopware-product-create',
            'shopware-revenue-report',
            'shopware-order-cancel',
            'shopware-bestseller-report',
            'shopware-cart-manage',
            'shopware-cart-checkout',
            'shopware-checkout-methods',
            'shopware-media-upload',
            'shopware-theme-config',
        ];
    }

    /**
     * All prompt names that must be present in prompts/list.
     *
     * @return list<string>
     */
    private static function expectedPrompts(): array
    {
        return [
            'shopware-context',
        ];
    }

    /**
     * All resource names that must be present in resources/list.
     *
     * @return list<string>
     */
    private static function expectedResources(): array
    {
        return [
            'shopware-entity-list',
            'shopware-sales-channels',
            'shopware-currencies',
            'shopware-languages',
            'shopware-state-machines',
            'shopware-business-events',
            'shopware-flow-actions',
        ];
    }

    /**
     * @return list<string>
     */
    private function listCapabilities(string $method, string $resultKey): array
    {
        $browser = $this->getBrowser();

        // Step 1: initialize the MCP session
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'mcp-discovery-test', 'version' => '1.0'],
                ],
                'id' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        $sessionId = $this->extractSessionId($browser->getResponse()->headers->all());

        // Step 2: call the list method
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ]),
            json_encode([
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => new \stdClass(),
                'id' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'MCP response was empty');

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('result', $response, 'MCP response missing result: ' . $content);

        $items = $response['result'][$resultKey] ?? [];

        return array_column($items, 'name');
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
