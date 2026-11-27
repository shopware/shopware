<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * A tool result reaches the client through the central renderer, in the format of the negotiated
 * protocol revision: the MCP format since 2025-06-18, the deprecated legacy envelope before.
 *
 * @internal
 */
#[Package('framework')]
class McpToolResultFormatTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    public function testAClientSince20250618GetsThePlainDataAndStructuredContent(): void
    {
        $result = $this->callTool('shopware-toolsets-list', [], '2025-06-18');

        static::assertFalse($result['isError'] ?? false);
        static::assertIsArray($result['structuredContent'] ?? null);
        static::assertArrayHasKey('toolsets', $result['structuredContent']);

        $text = json_decode($result['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('success', $text, 'the legacy envelope is only sent to clients before 2025-06-18');
        $structured = $result['structuredContent'];
        $meta = $structured['_meta'] ?? null;
        unset($structured['_meta']);
        static::assertEquals($text, $structured);
        static::assertEquals(json_decode($result['content'][1]['text'], true, 512, \JSON_THROW_ON_ERROR), ['_meta' => $meta], 'the metadata block and `structuredContent` carry the same metadata');
        static::assertArrayHasKey('shopware/generatedAt', $result['_meta'] ?? []);
    }

    public function testAClientSince20250618SeesADryRunInStructuredContent(): void
    {
        $arguments = ['entity' => 'tax', 'payload' => '{"name":"MCP dry run","taxRate":7}'];

        $result = $this->callTool('shopware-entity-upsert', $arguments, '2025-06-18');

        static::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        static::assertSame(['dryRun' => true], $result['structuredContent']['_meta'] ?? null);
        static::assertSame('upsert', $result['structuredContent']['result'][0]['operation'] ?? null);

        $legacy = $this->callTool('shopware-entity-upsert', $arguments, '2025-03-26');

        static::assertArrayNotHasKey('structuredContent', $legacy);
        $text = json_decode($legacy['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(['dryRun' => true], $text['_meta'] ?? null);
    }

    public function testAClientBefore20250618GetsTheLegacyEnvelopeOnly(): void
    {
        $result = $this->callTool('shopware-toolsets-list', [], '2025-03-26');

        static::assertArrayNotHasKey('structuredContent', $result);
        static::assertCount(1, $result['content']);

        $text = json_decode($result['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($text['success']);
        static::assertArrayHasKey('toolsets', $text['data']);
    }

    public function testAFailedCallIsReportedAsIsErrorOnEveryRevision(): void
    {
        $result = $this->callTool('shopware-entity-schema', ['entity' => 'no_such_entity'], '2025-06-18');

        static::assertTrue($result['isError'] ?? false);
        static::assertSame('invalid_arguments', $result['structuredContent']['error']['code'] ?? null);
        static::assertStringContainsString('no_such_entity', $result['structuredContent']['error']['message']);
        static::assertStringContainsString('no_such_entity', $result['content'][0]['text']);

        $legacy = $this->callTool('shopware-entity-schema', ['entity' => 'no_such_entity'], '2025-03-26');

        static::assertTrue($legacy['isError'] ?? false);
        $text = json_decode($legacy['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($text['success']);
        static::assertSame('invalid_arguments', $text['code']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function callTool(string $name, array $arguments, string $protocolVersion): array
    {
        $browser = $this->getBrowser();
        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => $protocolVersion,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-result-format-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        $sessionId = $browser->getResponse()->headers->get('mcp-session-id');
        static::assertIsString($sessionId, 'initialize did not return an MCP session id');

        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => (object) $arguments],
            'id' => 2,
        ], \JSON_THROW_ON_ERROR));

        $content = (string) $browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response['result'] ?? null, $content);

        return $response['result'];
    }
}
