<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpServerInstructions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpServerInstructions::class)]
class McpServerInstructionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shippedInstructionsProvider(): iterable
    {
        yield 'Admin API' => [McpServerInstructions::ADMIN, McpServerInstructions::ADMIN_STATELESS];
        yield 'Store API' => [McpServerInstructions::STORE_API, McpServerInstructions::STORE_API_STATELESS];
    }

    #[DataProvider('shippedInstructionsProvider')]
    public function testStatelessTwinPointsToToolsetsInTheUrlInsteadOfTheToolsetSessionTools(string $handshake, string $stateless): void
    {
        static::assertStringContainsString('shopware-toolset-enable', $handshake);
        static::assertStringContainsString('shopware-tool-search', $stateless);
        static::assertStringContainsString('?toolsets=', $stateless);
        static::assertStringNotContainsString('shopware-toolset-enable', $stateless);
        static::assertStringNotContainsString('shopware-toolsets-list', $stateless);
    }

    #[DataProvider('shippedInstructionsProvider')]
    public function testSwapsShippedInstructionsOfAStatelessDiscover(string $handshake, string $stateless): void
    {
        $response = self::discoverResponse($handshake);

        McpServerInstructions::applyToStatelessDiscover(self::request('server/discover', stateless: true), $response);

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($stateless, $body['result']['instructions']);
        static::assertStringContainsString('"tools":{}', (string) $response->getContent(), 'empty JSON objects must stay objects');
        static::assertSame((string) \strlen((string) $response->getContent()), $response->headers->get('Content-Length'));
    }

    /**
     * @return iterable<string, array{Request, string}>
     */
    public static function untouchedProvider(): iterable
    {
        yield 'handshake request' => [self::request('server/discover', stateless: false), McpServerInstructions::ADMIN];
        yield 'other stateless method' => [self::request('tools/list', stateless: true), McpServerInstructions::ADMIN];
        yield 'instructions a project configured' => [self::request('server/discover', stateless: true), 'Custom instructions naming shopware-toolset-enable.'];
    }

    #[DataProvider('untouchedProvider')]
    public function testLeavesEveryOtherAnswerAlone(Request $request, string $instructions): void
    {
        $response = self::discoverResponse($instructions);
        $content = $response->getContent();

        McpServerInstructions::applyToStatelessDiscover($request, $response);

        static::assertSame($content, $response->getContent());
    }

    private static function discoverResponse(string $instructions): Response
    {
        $content = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'supportedVersions' => [ProtocolVersion::V2026_07_28->value],
                'capabilities' => ['tools' => new \stdClass()],
                'instructions' => $instructions,
                'resultType' => 'complete',
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/json', 'Content-Length' => (string) \strlen($content)]);
    }

    private static function request(string $method, bool $stateless): Request
    {
        $params = $stateless ? ['_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value]] : new \stdClass();

        return Request::create('/api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], \JSON_THROW_ON_ERROR));
    }
}
