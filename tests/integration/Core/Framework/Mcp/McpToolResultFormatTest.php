<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * A tool result reaches the client through the central renderer: `structuredContent` for the data,
 * `isError` for failures, and the text block clients and models already read.
 *
 * @internal
 */
#[Package('framework')]
class McpToolResultFormatTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @deprecated tag:v6.8.0 - Tests the legacy envelope, will be removed
     */
    public function testASuccessfulCallCarriesStructuredContentAndKeepsTheLegacyText(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $result = $this->callTool('shopware-toolsets-list', []);

        static::assertFalse($result['isError'] ?? false);
        static::assertIsArray($result['structuredContent'] ?? null);
        static::assertArrayHasKey('toolsets', $result['structuredContent']);

        $text = json_decode($result['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($text['success'], 'existing readers still get the legacy envelope');
        static::assertEquals($text['data'], $result['structuredContent']);
        static::assertArrayHasKey('shopware/generatedAt', $result['_meta'] ?? []);
    }

    /**
     * @deprecated tag:v6.8.0 - Tests the legacy envelope, will be removed
     */
    public function testAFailedCallIsReportedAsIsError(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $result = $this->callTool('shopware-entity-schema', ['entity' => 'no_such_entity']);

        static::assertTrue($result['isError'] ?? false);
        static::assertSame('invalid_arguments', $result['structuredContent']['error']['code'] ?? null);
        static::assertStringContainsString('no_such_entity', $result['structuredContent']['error']['message']);

        $text = json_decode($result['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($text['success']);
    }

    public function testTheFinalFormatSendsThePlainData(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $result = $this->callTool('shopware-toolsets-list', []);

        $text = json_decode($result['content'][0]['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('success', $text);
        static::assertEquals($text, $result['structuredContent']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function callTool(string $name, array $arguments): array
    {
        $browser = $this->getBrowser();
        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
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
