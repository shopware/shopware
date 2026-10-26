<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpProtocolEra;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpProtocolEra::class)]
class McpProtocolEraTest extends TestCase
{
    /**
     * @return iterable<string, array{Request|null, bool}>
     */
    public static function requestProvider(): iterable
    {
        yield 'no request' => [null, false];
        yield 'handshake request' => [self::request(['protocolVersion' => ProtocolVersion::V2025_11_25->value]), false];
        yield 'request claiming 2026-07-28' => [self::request(['_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value]]), true];
        yield 'GET stream' => [Request::create('/api/_mcp', 'GET'), false];
        yield 'unreadable body' => [Request::create('/api/_mcp', 'POST', content: '{'), false];
    }

    #[DataProvider('requestProvider')]
    public function testTellsTheEraBySdkRouting(?Request $request, bool $stateless): void
    {
        static::assertSame($stateless, McpProtocolEra::isStateless($request));
    }

    public function testKeepsTheAnswerOnTheRequest(): void
    {
        $request = self::request(['_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value]]);
        static::assertTrue(McpProtocolEra::isStateless($request));

        $request->initialize(attributes: $request->attributes->all(), content: '{}');
        static::assertTrue(McpProtocolEra::isStateless($request));
    }

    public function testUnavailableMessagePointsToToolsetsInTheUrlAndToolSearch(): void
    {
        $message = McpProtocolEra::toolsetToolUnavailableMessage('shopware-toolset-enable', 'order');

        static::assertStringContainsString('"?toolsets=order"', $message);
        static::assertStringContainsString('shopware-tool-search', $message);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function request(array $params): Request
    {
        return Request::create('/api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => $params,
        ], \JSON_THROW_ON_ERROR));
    }
}
