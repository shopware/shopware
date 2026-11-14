<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Authentication;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Mcp\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpOAuthMetadata;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\McpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpOAuthMetadata::class)]
class McpOAuthMetadataTest extends TestCase
{
    /**
     * @param array<string, string> $server
     */
    #[DataProvider('urlProvider')]
    public function testUrlsKeepTheBasePathAndInsertTheWellKnownSegmentAfterTheHost(
        string $uri,
        array $server,
        string $appUrl,
        string $expectedResource,
        string $expectedIssuer,
        string $expectedResourceMetadataUrl,
        string $expectedAuthorizationServerMetadataUrl,
    ): void {
        $metadata = $this->createMetadata(['shop.example'], $appUrl);
        $request = Request::create($uri, server: $server);

        static::assertSame($expectedResource, $metadata->getResource($request));
        static::assertSame($expectedIssuer, $metadata->getIssuer($request));
        static::assertSame($expectedResourceMetadataUrl, $metadata->getProtectedResourceMetadataUrl($request));
        static::assertSame($expectedAuthorizationServerMetadataUrl, $metadata->getAuthorizationServerMetadataUrl($request));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string, string, string, string, string}>
     */
    public static function urlProvider(): iterable
    {
        yield 'installation at the host root' => [
            'https://shop.example/api/_mcp',
            [],
            'https://fallback.example',
            'https://shop.example/api/_mcp',
            'https://shop.example/api',
            'https://shop.example/.well-known/oauth-protected-resource/api/_mcp',
            'https://shop.example/.well-known/oauth-authorization-server/api',
        ];

        yield 'installation in a subdirectory' => [
            'https://shop.example/shop/api/_mcp',
            ['SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => '/var/www/shop/index.php'],
            'https://fallback.example',
            'https://shop.example/shop/api/_mcp',
            'https://shop.example/shop/api',
            'https://shop.example/.well-known/oauth-protected-resource/shop/api/_mcp',
            'https://shop.example/.well-known/oauth-authorization-server/shop/api',
        ];

        yield 'non-default port' => [
            'http://shop.example:8000/api/_mcp',
            [],
            'https://fallback.example',
            'http://shop.example:8000/api/_mcp',
            'http://shop.example:8000/api',
            'http://shop.example:8000/.well-known/oauth-protected-resource/api/_mcp',
            'http://shop.example:8000/.well-known/oauth-authorization-server/api',
        ];

        yield 'unknown host falls back to APP_URL with its path' => [
            'https://attacker.example/api/_mcp',
            [],
            'https://fallback.example/shop/',
            'https://fallback.example/shop/api/_mcp',
            'https://fallback.example/shop/api',
            'https://fallback.example/.well-known/oauth-protected-resource/shop/api/_mcp',
            'https://fallback.example/.well-known/oauth-authorization-server/shop/api',
        ];
    }

    public function testDocumentsReferenceEachOther(): void
    {
        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');
        $request = Request::create('https://shop.example/.well-known/oauth-protected-resource/api/_mcp');

        $resource = $metadata->buildProtectedResourceMetadata($request);
        $authorizationServer = $metadata->buildAuthorizationServerMetadata($request);

        static::assertSame('https://shop.example/api/_mcp', $resource['resource']);
        static::assertSame([$authorizationServer['issuer']], $resource['authorization_servers']);
        static::assertSame('https://shop.example/api/oauth/authorize', $authorizationServer['authorization_endpoint']);
        static::assertSame('https://shop.example/api/oauth/token', $authorizationServer['token_endpoint']);
        static::assertSame(['S256'], $authorizationServer['code_challenge_methods_supported']);
        static::assertTrue($authorizationServer['authorization_response_iss_parameter_supported']);
    }

    public function testMetadataUrlKeepsTheQueryOfTheMcpRequestAsSent(): void
    {
        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');
        $request = Request::create('https://shop.example/api/_mcp');
        $request->server->set('QUERY_STRING', 'toolsets=orders,catalog&b=1&a=2');

        static::assertSame(
            'https://shop.example/.well-known/oauth-protected-resource/api/_mcp?toolsets=orders,catalog&b=1&a=2',
            $metadata->getProtectedResourceMetadataUrl($request)
        );
        static::assertSame('https://shop.example/api/_mcp', $metadata->getResource($request));
    }

    public function testMetadataDocumentEchoesTheQueryInTheResource(): void
    {
        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');
        $request = Request::create('https://shop.example/.well-known/oauth-protected-resource/api/_mcp');
        $request->server->set('QUERY_STRING', 'toolsets=orders,catalog&b=1&a=2');

        $document = $metadata->buildProtectedResourceMetadata($request);

        static::assertSame('https://shop.example/api/_mcp?toolsets=orders,catalog&b=1&a=2', $document['resource']);
        static::assertSame(['https://shop.example/api'], $document['authorization_servers']);
        static::assertSame(
            'https://shop.example/api/_mcp',
            $metadata->matchResource(Request::create('https://shop.example/api/oauth/authorize'), $document['resource'])
        );
    }

    public function testEmptyQueryAddsNoQuestionMark(): void
    {
        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');
        $request = Request::create('https://shop.example/.well-known/oauth-protected-resource/api/_mcp?');

        static::assertSame('https://shop.example/api/_mcp', $metadata->buildProtectedResourceMetadata($request)['resource']);
    }

    public function testProtectedResourceMetadataHasTheRfc9728Shape(): void
    {
        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');
        $request = Request::create('https://shop.example/.well-known/oauth-protected-resource/api/_mcp');

        // The key order differs between mcp/sdk 0.8 and 0.9; JSON objects are unordered.
        static::assertEquals([
            'resource' => 'https://shop.example/api/_mcp',
            'authorization_servers' => ['https://shop.example/api'],
            'bearer_methods_supported' => ['header'],
        ], $metadata->buildProtectedResourceMetadata($request));
    }

    public function testProtectedResourceMetadataAcceptsPlainHttpOnLoopbackHosts(): void
    {
        $metadata = $this->createMetadata([], 'http://localhost:8000');
        $request = Request::create('http://unknown.example/.well-known/oauth-protected-resource/api/_mcp');

        static::assertSame('http://localhost:8000/api/_mcp', $metadata->buildProtectedResourceMetadata($request)['resource']);
    }

    public function testProtectedResourceMetadataIsUnavailableWhenTheSdkRejectsPlainHttp(): void
    {
        if (!InstalledVersions::satisfies(new VersionParser(), 'mcp/sdk', '>=0.9')) {
            static::markTestSkipped('mcp/sdk before 0.9 accepts plain http on any host.');
        }

        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');
        $request = Request::create('http://shop.example/.well-known/oauth-protected-resource/api/_mcp');

        try {
            $metadata->buildProtectedResourceMetadata($request);
            static::fail('The SDK accepted plain http on a non-loopback host.');
        } catch (McpException $exception) {
            static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
            static::assertSame('MCP__OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE', $exception->getErrorCode());
            static::assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }

    #[DataProvider('resourceProvider')]
    public function testMatchResourceAcceptsOnlyTheMcpResourceOfThisShop(string $requested, ?string $expected): void
    {
        $metadata = $this->createMetadata(['shop.example'], 'https://fallback.example');

        static::assertSame($expected, $metadata->matchResource(Request::create('https://shop.example/api/oauth/authorize'), $requested));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function resourceProvider(): iterable
    {
        yield 'canonical resource' => ['https://shop.example/api/_mcp', 'https://shop.example/api/_mcp'];
        yield 'trailing slash, upper case host and default port' => ['https://SHOP.example:443/api/_mcp/', 'https://shop.example/api/_mcp'];
        yield 'connection options in the query' => ['https://shop.example/api/_mcp?toolsets=catalog', 'https://shop.example/api/_mcp'];
        yield 'another Admin API path' => ['https://shop.example/api', null];
        yield 'another host' => ['https://other.example/api/_mcp', null];
        yield 'another scheme' => ['http://shop.example/api/_mcp', null];
        yield 'fragment' => ['https://shop.example/api/_mcp#x', null];
        yield 'relative' => ['/api/_mcp', null];
    }

    /**
     * @param list<string> $allowedHosts
     */
    private function createMetadata(array $allowedHosts, string $appUrl): McpOAuthMetadata
    {
        $allowedHostsProvider = static::createStub(McpAllowedHostsProvider::class);
        $allowedHostsProvider->method('getAllowedHosts')->willReturn($allowedHosts);

        return new McpOAuthMetadata($allowedHostsProvider, $appUrl);
    }
}
