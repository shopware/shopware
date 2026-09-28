<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * End-to-end capability discovery test.
 *
 * Calls the live MCP HTTP endpoint (/api/_mcp) using the JSON-RPC protocol
 * and asserts that every expected default tool, prompt, and resource name is present
 * in the server's response. Additional tools are discovered through shopware-tool-search.
 *
 * This validates the full registration stack:
 *   Core tools:   #[McpTool] attribute → mcp.tool DI tag → server assigned by the namespace
 *                 prefixes in packages/mcp.php
 *   Plugin tools: services.xml shopware.mcp.tool tag → McpToolDiscoveryCompilerPass → mcp.tool tag
 *                 → assigned to the Admin API server via mcp.servers.elements
 *
 * Unit tests that only load mcp.php do not catch a capability that ends up assigned to no server
 * (the failure mode that replaced the old scan_dirs gap, which once made shopware-theme-config
 * silently disappear). This test does.
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
        static::assertContains(
            $name,
            $this->listCapabilities('tools/list', 'tools'),
            \sprintf(
                'Tool "%s" is missing from tools/list. Check the mcp.tool DI tag, and that the class is claimed by the admin server in packages/mcp.php (see debug:mcp --native).',
                $name,
            ),
        );
    }

    #[DataProvider('expectedPrompts')]
    public function testExpectedPromptIsDiscovered(string $name): void
    {
        static::assertContains(
            $name,
            $this->listCapabilities('prompts/list', 'prompts'),
            \sprintf('Prompt "%s" is missing from prompts/list.', $name),
        );
    }

    #[DataProvider('expectedResources')]
    public function testExpectedResourceIsDiscovered(string $name): void
    {
        static::assertContains(
            $name,
            $this->listCapabilities('resources/list', 'resources'),
            \sprintf('Resource "%s" is missing from resources/list.', $name),
        );
    }

    public function testInitializeResponseInstructionsGuideToolDiscovery(): void
    {
        $browser = $this->getBrowser();
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

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'MCP response was empty');

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('result', $response, 'MCP initialize missing result: ' . $content);

        $instructions = $response['result']['instructions'] ?? '';
        static::assertIsString($instructions);
        static::assertStringContainsString(
            'shopware-tool-search',
            $instructions,
            'Server instructions must point clients at shopware-tool-search when no advertised tool matches the requested action.',
        );
    }

    public function testFreshSessionAdvertisesOnlyDiscoveryTools(): void
    {
        $browser = $this->getBrowser();

        // Initialize a session but do NOT enable any toolset.
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
                'method' => 'tools/list',
                'params' => new \stdClass(),
                'id' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'MCP response was empty');

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('result', $response, 'MCP response missing result: ' . $content);

        $tools = array_column($response['result']['tools'] ?? [], 'name');

        // The discovery interface is always advertised...
        static::assertContains('shopware-tool-search', $tools);
        static::assertContains('shopware-toolset-enable', $tools);
        static::assertContains('shopware-toolsets-list', $tools);

        // ...but no domain tool is, until its toolset is enabled.
        static::assertNotContains('shopware-entity-schema', $tools);
        static::assertNotContains('shopware-entity-search', $tools);
        static::assertNotContains('shopware-entity-read', $tools);
        static::assertNotContains('shopware-entity-delete', $tools);
        static::assertNotContains('shopware-system-config-read', $tools);
        static::assertNotContains('shopware-order-state', $tools);
    }

    public function testEnablingToolsetDeliversToolsListChangedNotification(): void
    {
        $browser = $this->getBrowser();

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
        static::assertIsString($sessionId, 'initialize did not return an MCP session id');

        $toolsetRegistry = static::getContainer()->get(McpToolsetRegistry::class);
        static::assertInstanceOf(McpToolsetRegistry::class, $toolsetRegistry);
        $toolsets = $toolsetRegistry->toolsets();
        static::assertNotEmpty($toolsets, 'expected at least one enable-able toolset');
        $toolsetName = $toolsets[0]['name'];

        // Enable a toolset via the actual tool call. The notification is not part of this response;
        // it is queued after the SDK saves its session and drained on the client's next request.
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => ['name' => 'shopware-toolset-enable', 'arguments' => ['toolset' => $toolsetName]],
                'id' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $enableResponse = $browser->getResponse();
        static::assertSame(200, $enableResponse->getStatusCode());
        // The toolset-enable response itself is a single JSON-RPC object over application/json.
        static::assertStringStartsWith('application/json', (string) $enableResponse->headers->get('Content-Type'));
        static::assertNotContains(
            'notifications/tools/list_changed',
            $this->jsonRpcMethods((string) $enableResponse->getContent()),
            'the listChanged must be queued for the next request, not returned inside the toolset-enable response',
        );

        // The next request drains the queued notification alongside its own response. Per the MCP
        // spec these multiple messages must be delivered as an SSE stream (one single-object event
        // each), never as a JSON-RPC batch array over application/json.
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_MCP_SESSION_ID' => $sessionId],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => new \stdClass(),
                'id' => 3,
            ], \JSON_THROW_ON_ERROR),
        );

        $drainResponse = $browser->getResponse();
        static::assertStringStartsWith(
            'text/event-stream',
            (string) $drainResponse->headers->get('Content-Type'),
            'multiple MCP messages must be delivered as SSE, not a JSON array over application/json',
        );
        static::assertContains(
            'notifications/tools/list_changed',
            $this->jsonRpcMethods((string) $drainResponse->getContent()),
            'client never received tools/list_changed after enabling a toolset',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedTools(): iterable
    {
        yield 'shopware-toolsets-list' => ['shopware-toolsets-list'];
        yield 'shopware-toolset-enable' => ['shopware-toolset-enable'];
        yield 'shopware-entity-schema' => ['shopware-entity-schema'];
        yield 'shopware-entity-search' => ['shopware-entity-search'];
        yield 'shopware-tool-search' => ['shopware-tool-search'];
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

        if ($method === 'tools/list' && $sessionId !== null) {
            $this->enableAllToolsetsForSession($sessionId);
        }

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

    private function enableAllToolsetsForSession(string $sessionId): void
    {
        $toolsetRegistry = static::getContainer()->get(McpToolsetRegistry::class);
        static::assertInstanceOf(McpToolsetRegistry::class, $toolsetRegistry);

        $toolsetSessionStorage = static::getContainer()->get(McpToolsetSessionStorage::class);
        static::assertInstanceOf(McpToolsetSessionStorage::class, $toolsetSessionStorage);

        foreach ($toolsetRegistry->toolsets() as $toolset) {
            $toolsetSessionStorage->enable($sessionId, $toolset['name']);
        }
    }

    /**
     * Extracts the JSON-RPC "method" of every message in an MCP response body, which is either a
     * single JSON object over application/json or one single-object SSE event per message over
     * text/event-stream.
     *
     * @return list<string>
     */
    private function jsonRpcMethods(string $content): array
    {
        $trimmed = ltrim($content);

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            static::assertIsArray($decoded);
            $messages = \array_key_exists('jsonrpc', $decoded) ? [$decoded] : $decoded;
        } else {
            $messages = [];
            foreach (explode("\n", $content) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $messages[] = json_decode(trim(substr($line, 5)), true, 512, \JSON_THROW_ON_ERROR);
                }
            }
        }

        $methods = [];
        foreach ($messages as $message) {
            if (\is_array($message) && \is_string($message['method'] ?? null)) {
                $methods[] = $message['method'];
            }
        }

        return $methods;
    }
}
