<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * A tool result too large to return inline is stored and linked with a `resource_link`. The link is a
 * signed pointer for the caller, so it can be read on a later request, and only by the same caller.
 *
 * @internal
 */
#[Package('framework')]
class McpToolResultLinkTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testALargeAdminResultIsLinkedAndReadableOnALaterRequest(): void
    {
        $browser = $this->getBrowser();
        $sessionId = $this->initialize($browser, '/api/_mcp');

        $result = $this->rpc($browser, '/api/_mcp', $sessionId, 'tools/call', [
            'name' => 'shopware-entity-search',
            'arguments' => ['entity' => 'country', 'criteria' => '{"associations":{"states":{}}}', 'limit' => 500],
        ]);

        // The link follows the text blocks, whose number depends on the format: the legacy envelope is
        // one block, the 6.8 format adds the summary and the metadata as blocks of their own.
        $content = $result['content'] ?? [];
        static::assertIsArray($content);
        $links = array_values(array_filter(
            $content,
            static fn (mixed $block): bool => \is_array($block) && ($block['type'] ?? null) === 'resource_link',
        ));
        $link = $links[0] ?? null;
        static::assertIsArray($link, 'expected a resource_link after the text blocks: ' . json_encode($result));
        static::assertCount(1, $links);
        static::assertStringStartsWith('shopware://tool-result/', $link['uri']);
        static::assertArrayHasKey('shopware/expiresAt', $result['_meta'] ?? []);

        // The pointer does not depend on the MCP session: a new session of the same user reads it.
        $read = $this->rpc($browser, '/api/_mcp', $this->initialize($browser, '/api/_mcp'), 'resources/read', ['uri' => $link['uri']]);

        $stored = json_decode($read['contents'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($stored['success']);
        static::assertNotEmpty($stored['data']);
    }

    public function testAnotherAdminPrincipalCannotReadTheLink(): void
    {
        $pointer = static::getContainer()->get(ToolResultCacheStorage::class)->storeFor('admin:' . Uuid::randomHex() . ':', '{"success":true,"data":[]}');

        $browser = $this->getBrowser();
        $response = $this->rpcResponse($browser, '/api/_mcp', $this->initialize($browser, '/api/_mcp'), 'resources/read', ['uri' => $pointer->uri()]);

        static::assertArrayHasKey('error', $response, 'another integration must not read the result');
        static::assertStringContainsString('not found', $response['error']['message']);
    }

    public function testAStoreResultIsReadableInTheSameSalesChannelContextOnly(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $principal = 'store:' . $this->salesChannelIdOf($browser) . ':' . $browser->getServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN);
        $pointer = static::getContainer()->get(ToolResultCacheStorage::class)->storeFor($principal, '{"success":true,"data":{"a":1}}');

        $read = $this->rpc($browser, '/store-api/_mcp', $this->initialize($browser, '/store-api/_mcp'), 'resources/read', ['uri' => $pointer->uri()]);
        static::assertSame('{"success":true,"data":{"a":1}}', $read['contents'][0]['text']);

        $otherVisitor = $this->createSalesChannelBrowser();
        $response = $this->rpcResponse($otherVisitor, '/store-api/_mcp', $this->initialize($otherVisitor, '/store-api/_mcp'), 'resources/read', ['uri' => $pointer->uri()]);
        static::assertArrayHasKey('error', $response, 'another context token must not read the result');
        static::assertStringContainsString('not found', $response['error']['message']);
    }

    private function salesChannelIdOf(KernelBrowser $browser): string
    {
        $id = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `sales_channel` WHERE `access_key` = :accessKey',
            ['accessKey' => $browser->getServerParameter('HTTP_SW_ACCESS_KEY')],
        );
        static::assertIsString($id);

        return $id;
    }

    private function initialize(KernelBrowser $browser, string $path): string
    {
        $browser->request('POST', $path, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-result-link-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $sessionId = $browser->getResponse()->headers->get('mcp-session-id');
        static::assertIsString($sessionId, 'initialize did not return an MCP session id: ' . $browser->getResponse()->getContent());

        return $sessionId;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpc(KernelBrowser $browser, string $path, string $sessionId, string $method, array $params): array
    {
        $response = $this->rpcResponse($browser, $path, $sessionId, $method, $params);
        static::assertIsArray($response['result'] ?? null, (string) json_encode($response));

        return $response['result'];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpcResponse(KernelBrowser $browser, string $path, string $sessionId, string $method, array $params): array
    {
        $browser->request('POST', $path, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId], json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 2,
        ], \JSON_THROW_ON_ERROR));

        $content = (string) $browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response, $content);

        return $response;
    }
}
