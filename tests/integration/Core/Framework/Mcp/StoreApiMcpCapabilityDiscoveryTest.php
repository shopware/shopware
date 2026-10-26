<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class StoreApiMcpCapabilityDiscoveryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testStoreApiMcpAdvertisesReducedMetaToolSet(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $sessionId = $this->initialize($browser);

        $tools = $this->listTools($browser, $sessionId);

        // Progressive disclosure: only the default meta-tools are advertised up front.
        static::assertContains('shopware-tool-search', $tools);
        static::assertContains('shopware-toolsets-list', $tools);
        static::assertContains('shopware-toolset-enable', $tools);
        // Store API tools live in their toolset and are hidden until enabled.
        static::assertNotContains('shopware-store-api-context', $tools);
        // Admin tools never leak into the store-api registry.
        static::assertNotContains('shopware-entity-search', $tools);
        static::assertNotContains('shopware-theme-config', $tools);
    }

    public function testStoreApiMcpToolSearchCarriesEnableUsageHint(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $sessionId = $this->initialize($browser);

        $result = $this->callTool($browser, $sessionId, 'shopware-tool-search', ['query' => 'context']);
        $meta = $this->toolPayload($result)['_meta'];

        static::assertArrayHasKey('usage', $meta);
        static::assertIsString($meta['usage']);
        static::assertStringContainsString('shopware-toolset-enable', $meta['usage']);
    }

    public function testStoreApiMcpToolsetEnableRevealsHiddenToolsAndSignalsListChanged(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $sessionId = $this->initialize($browser);

        // The store-api toolset is listed and not yet enabled.
        $toolsets = $this->callTool($browser, $sessionId, 'shopware-toolsets-list', []);
        $listData = $this->toolPayload($toolsets)['data'];
        static::assertIsArray($listData);
        $listedToolsets = $listData['toolsets'] ?? null;
        static::assertIsArray($listedToolsets);
        $storeApiToolset = null;
        foreach ($listedToolsets as $toolset) {
            static::assertIsArray($toolset);
            if ($toolset['name'] === 'store-api') {
                $storeApiToolset = $toolset;
            }
        }
        static::assertNotNull($storeApiToolset, 'store-api toolset should be listed: ' . $toolsets['content'][0]['text']);
        static::assertFalse($storeApiToolset['enabled']);
        static::assertContains('shopware-store-api-context', $storeApiToolset['tools']);

        // Enabling it reports listChanged.
        $enable = $this->callTool($browser, $sessionId, 'shopware-toolset-enable', ['toolset' => 'store-api']);
        static::assertTrue($this->toolPayload($enable)['_meta']['listChanged'] ?? false);

        // After enabling, the previously hidden tool is advertised.
        $tools = $this->listTools($browser, $sessionId);
        static::assertContains('shopware-store-api-context', $tools);
    }

    public function testStoreApiInitializeResponseInstructionsGuideToolDiscovery(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $browser->request('POST', '/store-api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'store-api-mcp-discovery-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'Store API MCP response was empty');

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('result', $response, 'Store API MCP initialize missing result: ' . $content);

        $instructions = $response['result']['instructions'] ?? '';
        static::assertIsString($instructions);
        static::assertStringContainsString(
            'shopware-tool-search',
            $instructions,
            'Store API server instructions must point clients at shopware-tool-search when no advertised tool matches the requested action.',
        );
    }

    public function testStoreApiInitializeDeclaresToolsListChanged(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $browser->request('POST', '/store-api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'store-api-mcp-discovery-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);

        // shopware-toolset-enable queues notifications/tools/list_changed on this era.
        static::assertTrue($response['result']['capabilities']['tools']['listChanged'] ?? null);
    }

    public function testStoreApiToolResultsGoThroughTheResultRenderer(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $sessionId = $this->initialize($browser);

        $result = $this->callTool($browser, $sessionId, 'shopware-toolsets-list', []);

        static::assertArrayHasKey('shopware/generatedAt', $result['_meta'] ?? [], 'the Store API server must wrap its reference handler too');
    }

    private function initialize(KernelBrowser $browser): string
    {
        $browser->request('POST', '/store-api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'store-api-mcp-discovery-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $response = $browser->getResponse();
        $sessionId = $response->headers->get('mcp-session-id');
        static::assertNotNull($sessionId, 'initialize response is missing the mcp-session-id header');

        static::assertContains(
            PlatformRequest::HEADER_MCP_SESSION_ID,
            explode(',', (string) $response->headers->get('Access-Control-Expose-Headers')),
            'mcp-session-id must be exposed via CORS for browser-based MCP clients',
        );

        return $sessionId;
    }

    /**
     * @return list<string>
     */
    private function listTools(KernelBrowser $browser, string $sessionId): array
    {
        $result = $this->rpc($browser, $sessionId, ['jsonrpc' => '2.0', 'method' => 'tools/list', 'params' => new \stdClass(), 'id' => 2]);

        return array_column($result['tools'] ?? [], 'name');
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{content: list<array{text: string}>, _meta?: array<string, mixed>}
     */
    private function callTool(KernelBrowser $browser, string $sessionId, string $name, array $arguments): array
    {
        /** @var array{content: list<array{text: string}>, _meta?: array<string, mixed>} $result */
        $result = $this->rpc($browser, $sessionId, [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments === [] ? new \stdClass() : $arguments],
            'id' => 3,
        ]);

        return $result;
    }

    /**
     * The tool's data and metadata from the legacy envelope, which this test's 2025-03-26 client gets.
     *
     * @param array{content: list<array{text: string}>, _meta?: array<string, mixed>} $result
     *
     * @return array{data: mixed, _meta: array<mixed>}
     */
    private function toolPayload(array $result): array
    {
        $envelope = json_decode($result['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($envelope);
        $meta = $envelope['_meta'] ?? [];
        static::assertIsArray($meta);

        return ['data' => $envelope['data'] ?? null, '_meta' => $meta];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function rpc(KernelBrowser $browser, string $sessionId, array $body): array
    {
        $browser->request(
            'POST',
            '/store-api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId],
            json_encode($body, \JSON_THROW_ON_ERROR),
        );

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), 'Store API MCP endpoint returned non-200');
        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content, 'Store API MCP response was empty');

        // A request issued right after a toolset-enable also drains the queued
        // notifications/tools/list_changed. Per the MCP spec those multiple messages are delivered
        // as a text/event-stream (one single-object event each), so parse either shape and return
        // the result of the message that carries one.
        foreach ($this->decodeMcpMessages($content) as $message) {
            if (\array_key_exists('result', $message)) {
                return $message['result'];
            }
        }

        static::fail('Store API MCP response missing result: ' . $content);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeMcpMessages(string $content): array
    {
        $trimmed = ltrim($content);

        if ($trimmed !== '' && $trimmed[0] === '{') {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            static::assertIsArray($decoded);

            return [$decoded];
        }

        $messages = [];
        foreach (explode("\n", $content) as $line) {
            if (str_starts_with($line, 'data:')) {
                $decoded = json_decode(trim(substr($line, 5)), true, 512, \JSON_THROW_ON_ERROR);
                static::assertIsArray($decoded);
                $messages[] = $decoded;
            }
        }

        return $messages;
    }
}
