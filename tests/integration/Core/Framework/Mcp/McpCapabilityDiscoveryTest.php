<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
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
 *   Core tools:   mcp.yaml scan_dirs → #[McpTool] attribute → mcp.tool DI tag
 *   Plugin tools: services.xml shopware.mcp.tool tag → McpToolCompilerPass → mcp.tool tag
 *
 * Unit tests that only load mcp.php do not catch scan_dirs problems (the gap
 * that caused shopware-theme-config to silently disappear). This test does.
 *
 * Note: custom/plugins is intentionally NOT in scan_dirs. Plugin tools must be
 * registered via the shopware.mcp.tool DI tag so they respect plugin lifecycle.
 *
 * @internal
 */
#[Package('framework')]
class McpCapabilityDiscoveryTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    #[DataProvider('expectedTools')]
    public function testExpectedToolIsDiscovered(string $name): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        static::assertContains(
            $name,
            $this->listCapabilities('tools/list', 'tools'),
            \sprintf(
                'Tool "%s" is missing from tools/list. Check mcp.yaml scan_dirs (core tools) or mcp.tool DI tag (plugin tools).',
                $name,
            ),
        );
    }

    #[DataProvider('expectedPrompts')]
    public function testExpectedPromptIsDiscovered(string $name): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        static::assertContains(
            $name,
            $this->listCapabilities('prompts/list', 'prompts'),
            \sprintf('Prompt "%s" is missing from prompts/list.', $name),
        );
    }

    #[DataProvider('expectedResources')]
    public function testExpectedResourceIsDiscovered(string $name): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        static::assertContains(
            $name,
            $this->listCapabilities('resources/list', 'resources'),
            \sprintf('Resource "%s" is missing from resources/list.', $name),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedTools(): iterable
    {
        yield 'shopware-entity-schema' => ['shopware-entity-schema'];
        yield 'shopware-entity-search' => ['shopware-entity-search'];
        yield 'shopware-entity-aggregate' => ['shopware-entity-aggregate'];
        yield 'shopware-entity-read' => ['shopware-entity-read'];
        yield 'shopware-entity-upsert' => ['shopware-entity-upsert'];
        yield 'shopware-entity-delete' => ['shopware-entity-delete'];
        yield 'shopware-system-config-read' => ['shopware-system-config-read'];
        yield 'shopware-system-config-write' => ['shopware-system-config-write'];
        yield 'shopware-order-state' => ['shopware-order-state'];
        yield 'shopware-media-upload' => ['shopware-media-upload'];
        yield 'shopware-theme-config' => ['shopware-theme-config'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedPrompts(): iterable
    {
        yield 'shopware-context' => ['shopware-context'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedResources(): iterable
    {
        yield 'shopware-entity-list' => ['shopware-entity-list'];
        yield 'shopware-sales-channels' => ['shopware-sales-channels'];
        yield 'shopware-currencies' => ['shopware-currencies'];
        yield 'shopware-languages' => ['shopware-languages'];
        yield 'shopware-state-machines' => ['shopware-state-machines'];
        yield 'shopware-business-events' => ['shopware-business-events'];
        yield 'shopware-flow-actions' => ['shopware-flow-actions'];
        yield 'shopware-extensions' => ['shopware-extensions'];
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

        static::assertSame(200, $browser->getResponse()->getStatusCode(), 'MCP endpoint returned non-200 status');

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
