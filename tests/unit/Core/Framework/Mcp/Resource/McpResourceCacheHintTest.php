<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Enum\CacheScope;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\McpResourceCacheHint;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpResourceCacheHint::class)]
class McpResourceCacheHintTest extends TestCase
{
    private const CONTENTS = ['uri' => 'shopware://currencies', 'mimeType' => 'application/json', 'text' => '[]'];

    public function testTheStatelessRevisionGetsAPrivateTtl(): void
    {
        $context = $this->context(ProtocolVersion::V2026_07_28->value, null);

        $result = McpResourceCacheHint::read(self::CONTENTS, $context, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);

        static::assertSame(McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS, $result->ttlMs);
        static::assertSame(CacheScope::Private, $result->cacheScope);
        static::assertSame(300_000, $result->jsonSerialize()['ttlMs'] ?? null);
        static::assertSame('private', $result->jsonSerialize()['cacheScope'] ?? null);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function handshakeRevisionProvider(): iterable
    {
        yield '2025-11-25' => [ProtocolVersion::V2025_11_25->value];
        yield '2024-11-05' => [ProtocolVersion::V2024_11_05->value];
        yield 'no negotiated revision' => [null];
    }

    /**
     * The handshake revisions do not know the hints, so their answer stays exactly as before.
     */
    #[DataProvider('handshakeRevisionProvider')]
    public function testHandshakeRevisionsGetNoHints(?string $sessionVersion): void
    {
        $result = McpResourceCacheHint::read(self::CONTENTS, $this->context(null, $sessionVersion), McpResourceCacheHint::DEPLOYMENT_TTL_MS);

        static::assertNull($result->ttlMs);
        static::assertNull($result->cacheScope);
        static::assertArrayNotHasKey('ttlMs', $result->jsonSerialize());
        static::assertArrayNotHasKey('cacheScope', $result->jsonSerialize());
    }

    public function testWithoutRequestContextThereAreNoHints(): void
    {
        $result = McpResourceCacheHint::read(self::CONTENTS, null, McpResourceCacheHint::DEPLOYMENT_TTL_MS);

        static::assertNull($result->ttlMs);
        static::assertNull($result->cacheScope);
    }

    public function testTheContentsAreOneTextBlock(): void
    {
        $result = McpResourceCacheHint::read(self::CONTENTS, null, 0);

        static::assertCount(1, $result->contents);
        $contents = $result->contents[0];
        static::assertInstanceOf(TextResourceContents::class, $contents);
        static::assertSame('shopware://currencies', $contents->uri);
        static::assertSame('application/json', $contents->mimeType);
        static::assertSame('[]', $contents->text);
    }

    private function context(?string $metaVersion, ?string $sessionVersion): RequestContext
    {
        $request = new ReadResourceRequest(self::CONTENTS['uri']);
        if ($metaVersion !== null) {
            $request = $request->withMeta(['io.modelcontextprotocol/protocolVersion' => $metaVersion]);
        }

        $session = static::createStub(SessionInterface::class);
        $session->method('get')->willReturnCallback(
            static fn (string $key): ?string => $key === 'protocol_version' ? $sessionVersion : null,
        );

        return new RequestContext($session, $request);
    }
}
