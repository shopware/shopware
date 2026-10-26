<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Clients decide from the tool annotations whether to ask the user before a call, so every core tool
 * must arrive with them in `tools/list` on both endpoints, through the allowlist list handler and the
 * toolset filter, and inside the tool definitions that `shopware-tool-search` returns.
 *
 * @internal
 */
#[Package('framework')]
class McpToolAnnotationsTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const READ_ONLY = ['readOnlyHint' => true, 'openWorldHint' => false];

    private const SESSION_STATE = ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];

    /**
     * @param array<string, bool> $expected
     */
    #[DataProvider('adminTools')]
    public function testAdminToolsListReturnsTheAnnotations(string $protocolVersion, string $toolName, array $expected): void
    {
        $browser = $this->getBrowser();
        $sessionId = $this->initialize($browser, '/api/_mcp', $protocolVersion);

        $toolsetSessionStorage = static::getContainer()->get(McpToolsetSessionStorage::class);
        foreach (static::getContainer()->get(McpToolsetRegistry::class)->toolsets() as $toolset) {
            $toolsetSessionStorage->enable($sessionId, $toolset['name']);
        }

        $tools = $this->listTools($browser, '/api/_mcp', $sessionId);

        static::assertArrayHasKey($toolName, $tools, \sprintf('%s is missing from tools/list', $toolName));
        static::assertSame($expected, $tools[$toolName]['annotations'] ?? null);
    }

    public static function adminTools(): \Generator
    {
        $tools = [
            'shopware-entity-schema' => self::READ_ONLY,
            'shopware-entity-search' => self::READ_ONLY,
            'shopware-entity-read' => self::READ_ONLY,
            'shopware-entity-aggregate' => self::READ_ONLY,
            'shopware-system-config-read' => self::READ_ONLY,
            'shopware-tool-search' => self::READ_ONLY,
            'shopware-toolsets-list' => self::READ_ONLY,
            'shopware-toolset-enable' => self::SESSION_STATE,
            'shopware-entity-upsert' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false],
            'shopware-media-upload' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true],
            'shopware-entity-delete' => ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false],
            'shopware-order-state' => ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
            'shopware-system-config-write' => ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false],
            'shopware-theme-config' => ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false],
        ];

        foreach (self::protocolVersions() as $protocolVersion) {
            foreach ($tools as $name => $expected) {
                yield $protocolVersion . ' ' . $name => [$protocolVersion, $name, $expected];
            }
        }
    }

    /**
     * @param array<string, bool> $expected
     */
    #[DataProvider('storeApiTools')]
    public function testStoreApiToolsListReturnsTheAnnotations(string $protocolVersion, string $toolName, array $expected): void
    {
        $browser = $this->createSalesChannelBrowser();
        $sessionId = $this->initialize($browser, '/store-api/_mcp', $protocolVersion);
        $this->rpc($browser, '/store-api/_mcp', $sessionId, 'tools/call', [
            'name' => McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'arguments' => ['toolset' => 'store-api'],
        ]);

        $tools = $this->listTools($browser, '/store-api/_mcp', $sessionId);

        static::assertArrayHasKey($toolName, $tools, \sprintf('%s is missing from tools/list', $toolName));
        static::assertSame($expected, $tools[$toolName]['annotations'] ?? null);
    }

    public static function storeApiTools(): \Generator
    {
        $tools = [
            'shopware-store-api-context' => self::READ_ONLY,
            'shopware-tool-search' => self::READ_ONLY,
            'shopware-toolsets-list' => self::READ_ONLY,
            'shopware-toolset-enable' => self::SESSION_STATE,
        ];

        foreach (self::protocolVersions() as $protocolVersion) {
            foreach ($tools as $name => $expected) {
                yield $protocolVersion . ' ' . $name => [$protocolVersion, $name, $expected];
            }
        }
    }

    public function testToolSearchReturnsTheAnnotationsOfAMatchedTool(): void
    {
        $browser = $this->getBrowser();
        $sessionId = $this->initialize($browser, '/api/_mcp', '2025-11-25');

        $result = $this->rpc($browser, '/api/_mcp', $sessionId, 'tools/call', [
            'name' => 'shopware-tool-search',
            'arguments' => ['query' => 'delete entities', 'maxResults' => 20],
        ]);

        $matches = $result['structuredContent']['result'] ?? null;
        static::assertIsArray($matches, (string) json_encode($result));

        $tools = array_column(array_column($matches, 'tool'), null, 'name');
        static::assertArrayHasKey('shopware-entity-delete', $tools, (string) json_encode($result));
        static::assertSame(
            ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false],
            $tools['shopware-entity-delete']['annotations'] ?? null,
        );
    }

    /**
     * The first revision with tool annotations and the latest handshake revision.
     *
     * @return list<string>
     */
    private static function protocolVersions(): array
    {
        return ['2025-03-26', '2025-11-25'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function listTools(KernelBrowser $browser, string $path, string $sessionId): array
    {
        $tools = $this->rpc($browser, $path, $sessionId, 'tools/list', [])['tools'] ?? null;
        static::assertIsArray($tools);

        return array_column($tools, null, 'name');
    }

    private function initialize(KernelBrowser $browser, string $path, string $protocolVersion): string
    {
        $browser->request('POST', $path, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => $protocolVersion,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-tool-annotations-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $sessionId = $browser->getResponse()->headers->get('mcp-session-id');
        static::assertIsString($sessionId, 'initialize did not return an MCP session id: ' . $browser->getInternalResponse()->getContent());

        return $sessionId;
    }

    /**
     * Returns the result of the response message that carries one. A request right after a toolset was
     * enabled also drains the queued `notifications/tools/list_changed`, so the body may be an event stream.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpc(KernelBrowser $browser, string $path, string $sessionId, string $method, array $params): array
    {
        $browser->request('POST', $path, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId], json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
            'id' => 2,
        ], \JSON_THROW_ON_ERROR));

        // The internal response holds the body of a streamed response too, which an event stream may be.
        $content = $browser->getInternalResponse()->getContent();
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $content);

        $bodies = str_starts_with(ltrim($content), '{') ? [$content] : array_map(
            static fn (string $line): string => trim(substr($line, 5)),
            array_values(array_filter(explode("\n", $content), static fn (string $line): bool => str_starts_with($line, 'data:'))),
        );

        foreach ($bodies as $body) {
            $message = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
            if (\is_array($message) && \is_array($message['result'] ?? null)) {
                return $message['result'];
            }
        }

        static::fail('MCP response carries no result: ' . $content);
    }
}
