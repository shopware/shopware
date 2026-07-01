<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrlRequestContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\Doctrine\FakeResultFactory;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoResolver::class)]
class SeoResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function resolveDataProvider(): iterable
    {
        yield 'null case' => [
            '',
            '/',
        ];
        yield 'same content, leading, but trailing slash' => [
            '/seo-url',
            '/seo-url',
        ];
        yield 'same content, leading and trailing slash' => [
            '/seo-url/',
            '/seo-url/',
        ];
        yield 'no trailing slash' => [
            'seo-url',
            '/seo-url',
        ];
        yield 'trailing slash' => [
            'seo-url/',
            '/seo-url/',
        ];
        yield '2 levels, no trailing slash' => [
            'seo-url/nice-addition',
            '/seo-url/nice-addition',
        ];
        yield '2 levels, trailing slash' => [
            'seo-url/nice-addition/',
            '/seo-url/nice-addition/',
        ];
        yield 'lots of levels, no trailing slash' => [
            'seo-url/nice-addition/with/something/really/really/reaaaaally/long',
            '/seo-url/nice-addition/with/something/really/really/reaaaaally/long',
        ];
        yield 'lots of levels, trailing slash' => [
            'seo-url/nice-addition/with/something/really/really/reaaaaally/long/',
            '/seo-url/nice-addition/with/something/really/really/reaaaaally/long/',
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function resolveCanonicalDataProvider(): iterable
    {
        yield 'null case' => [
            '',
            '/',
        ];
        yield 'same content, leading, but trailing slash' => [
            '/Industrial-Kids',
            '/Industrial-Kids',
        ];
        yield 'same content, leading and trailing slash' => [
            '/Industrial-Kids/',
            '/Industrial-Kids/',
        ];
        yield 'no trailing slash' => [
            'Industrial-Kids',
            '/Industrial-Kids',
        ];
        yield 'trailing slash' => [
            'Industrial-Kids/',
            '/Industrial-Kids/',
        ];
        yield 'lots of levels, no trailing slash' => [
            'Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books',
            '/Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books',
        ];
        yield 'lots of levels, trailing slash' => [
            'Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books/',
            '/Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books/',
        ];
    }

    #[DataProvider('resolveDataProvider')]
    public function testResolveUrlWithIsCanonical(string $pathInfo, string $expected): void
    {
        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->getMockConnection($salesChannelId, true, $pathInfo));

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), $salesChannelId, $pathInfo));

        static::assertSame($expected, $resolved->pathInfo);
    }

    #[DataProvider('resolveCanonicalDataProvider')]
    public function testResolveUrlWithNotCanonical(string $pathInfo, string $expected): void
    {
        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->getMockConnection($salesChannelId, false, $pathInfo));

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), $salesChannelId, $pathInfo));

        static::assertSame($expected, $resolved->canonicalPathInfo);
    }

    public function testResolveIgnoresDeletedSeoUrls(): void
    {
        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->createSqliteConnectionWithDeletedSeoUrl($languageId, $salesChannelId));

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $salesChannelId, 'awesome-product'));

        static::assertSame('/default', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveUrlWithQueryStringReturnsCanonical(): void
    {
        $salesChannelId = Uuid::randomHex();
        $expectedPathInfo = '/detail/12345';

        $seoResolver = new SeoResolver($this->getMockConnection($salesChannelId, true, $expectedPathInfo));

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(
            Uuid::randomHex(),
            $salesChannelId,
            'Main-product/SWDEMO10001',
            'test=123',
        ));

        static::assertSame($expectedPathInfo, $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveUrlWithoutQueryStringPrefersPlainCanonical(): void
    {
        $salesChannelId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $firstResult = FakeResultFactory::createResult([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/plain',
                'seoPathInfo' => 'Main-product/SWDEMO10001',
            ],
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/query',
                'seoPathInfo' => 'Main-product/SWDEMO10001?test=123',
            ],
        ], $connection);
        $secondResult = FakeResultFactory::createResult([], $connection);

        $connection->method('executeQuery')->willReturn($firstResult, $secondResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), $salesChannelId, 'Main-product/SWDEMO10001'));

        static::assertSame('/detail/plain', $resolved->pathInfo);
        static::assertSame('Main-product/SWDEMO10001', $resolved->seoPathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveUrlWithPlainCanonicalAndQueryStringDoesNotSetCanonicalPathInfo(): void
    {
        $salesChannelId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $firstResult = FakeResultFactory::createResult([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/plain',
                'seoPathInfo' => 'Main-product/SWDEMO10001',
            ],
        ], $connection);
        $secondResult = FakeResultFactory::createResult([], $connection);

        $connection->method('executeQuery')->willReturn($firstResult, $secondResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(
            Uuid::randomHex(),
            $salesChannelId,
            'Main-product/SWDEMO10001',
            'utm=123',
        ));

        static::assertSame('/detail/plain', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveUrlWithFlagQueryStringDoesNotSetCanonicalPathInfo(): void
    {
        $salesChannelId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $firstResult = FakeResultFactory::createResult([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/flag',
                'seoPathInfo' => 'Latest-Product/SW10005?test12345',
            ],
        ], $connection);
        $secondResult = FakeResultFactory::createResult([], $connection);

        $connection->method('executeQuery')->willReturn($firstResult, $secondResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(
            Uuid::randomHex(),
            $salesChannelId,
            'Latest-Product/SW10005',
            'test12345=',
        ));

        static::assertSame('/detail/flag', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveUrlMatchesStoredFlagQueryVerbatim(): void
    {
        $salesChannelId = Uuid::randomHex();
        $storedSeoPath = 'Latest-Product/SW10005?test12345';

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $matchResult = FakeResultFactory::createResult([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/flag',
                'seoPathInfo' => $storedSeoPath,
            ],
        ], $connection);
        $emptyResult = FakeResultFactory::createResult([], $connection);

        // Return the stored row only when the verbatim flag candidate is among the bound params.
        // Symfony normalizes the request query `test12345` to `test12345=`, so without the raw
        // candidate the stored `?test12345` would never be matched by the exact-match SQL.
        $connection->method('executeQuery')->willReturnCallback(
            static fn (string $sql, array $params = []): Result => \in_array($storedSeoPath, $params, true)
                ? $matchResult
                : $emptyResult
        );

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(
            Uuid::randomHex(),
            $salesChannelId,
            'Latest-Product/SW10005',
            'test12345',
        ));

        static::assertSame('/detail/flag', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveUrlFallsBackToPlainPathWhenNoRowMatches(): void
    {
        $salesChannelId = Uuid::randomHex();

        // When no seo_url row matches (exact-match SQL returns nothing), the resolver
        // returns the requested path verbatim as a non-canonical result. The exact-match
        // (LIKE-removal) behaviour itself is covered by the integration test
        // testResolveSeoPathWithDifferentQueryStringDoesNotMatchCanonicalQuery against a real DB.
        $connection = $this->createMock(Connection::class);
        $emptyResult = FakeResultFactory::createResult([], $connection);
        $connection->method('executeQuery')->willReturn($emptyResult, $emptyResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(
            Uuid::randomHex(),
            $salesChannelId,
            'product-a',
            'promo=summer&utm=fb',
        ));

        static::assertSame('/product-a', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveThrowsWhenFeatureActive(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('Feature v6.8.0.0 must be active to assert the throw behaviour.');
        }

        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->getMockConnection($salesChannelId, true, '/seo-url'));

        $this->expectException(\Throwable::class);
        $seoResolver->resolve(Uuid::randomHex(), $salesChannelId, '/seo-url');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedResolveProjectsAllFieldsToArray(): void
    {
        $salesChannelId = Uuid::randomHex();
        $foreignId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $firstResult = FakeResultFactory::createResult([
            [
                'id' => $foreignId,
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product',
            ],
        ], $connection);
        $secondResult = FakeResultFactory::createResult([], $connection);

        $connection->method('executeQuery')->willReturn($firstResult, $secondResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);
        $data = $seoResolver->resolve(Uuid::randomHex(), $salesChannelId, 'awesome-product');

        static::assertSame('/detail/1234', $data['pathInfo']);
        static::assertTrue($data['isCanonical']);
        static::assertArrayHasKey('id', $data);
        static::assertSame($foreignId, $data['id']);
        static::assertArrayHasKey('seoPathInfo', $data);
        static::assertSame('awesome-product', $data['seoPathInfo']);
        static::assertArrayNotHasKey('canonicalPathInfo', $data);
    }

    public function testResolveUrlPrefersCanonicalRowWhoseQueryMatchesRequest(): void
    {
        $salesChannelId = Uuid::randomHex();

        // Two canonical rows for the same sales channel match the request: a plain row and a
        // query-bearing variant. The query-matching variant is returned SECOND, so only the
        // usort query tie-break (not insertion order) can make it win. This guards the core of
        // the fix: a request carrying `?test=123` must resolve to the row stored with that query.
        $connection = $this->createMock(Connection::class);
        $firstResult = FakeResultFactory::createResult([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/plain',
                'seoPathInfo' => 'Main-product/SWDEMO10001',
            ],
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => true,
                'pathInfo' => '/detail/variant',
                'seoPathInfo' => 'Main-product/SWDEMO10001?test=123',
            ],
        ], $connection);
        $secondResult = FakeResultFactory::createResult([], $connection);

        $connection->method('executeQuery')->willReturn($firstResult, $secondResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(
            Uuid::randomHex(),
            $salesChannelId,
            'Main-product/SWDEMO10001',
            'test=123',
        ));

        static::assertSame('/detail/variant', $resolved->pathInfo);
        static::assertSame('Main-product/SWDEMO10001?test=123', $resolved->seoPathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveUrlFallbackFindsCanonicalSiblingWhenFirstHitIsNotCanonical(): void
    {
        $salesChannelId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // First query: returns a non-canonical row pointing at /detail/1234.
        $firstResult = FakeResultFactory::createResult([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'isCanonical' => false,
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'old-slug',
            ],
        ], $connection);
        // Second query: fallback canonical lookup finds the canonical sibling for /detail/1234.
        $secondResult = FakeResultFactory::createResult([
            [
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-v2',
            ],
        ], $connection);

        $connection->method('executeQuery')->willReturn($firstResult, $secondResult);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $seoResolver = new SeoResolver($connection);

        $resolved = $seoResolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), $salesChannelId, 'old-slug'));

        static::assertSame('/detail/1234', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
        static::assertSame('/awesome-product-v2', $resolved->canonicalPathInfo);
    }

    private function getMockConnection(string $salesChannelId, bool $isCanonical, string $pathInfo): Connection&MockObject
    {
        $mock = $this->createMock(Connection::class);
        $firstResult = FakeResultFactory::createResult([[
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'isCanonical' => $isCanonical,
            'pathInfo' => $pathInfo,
        ]], $mock);
        $canonicalResult = FakeResultFactory::createResult([[
            'id' => Uuid::randomHex(),
            'isCanonical' => $isCanonical,
            'seoPathInfo' => $pathInfo,
        ]], $mock);

        $mock
            ->method('executeQuery')
            ->willReturn($firstResult, $canonicalResult);
        $mock
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        return $mock;
    }

    private function createSqliteConnectionWithDeletedSeoUrl(string $languageId, string $salesChannelId): Connection
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $connection->executeStatement('
            CREATE TABLE seo_url (
                id BLOB PRIMARY KEY NOT NULL,
                sales_channel_id BLOB NULL,
                language_id BLOB NOT NULL,
                foreign_key BLOB NOT NULL,
                route_name VARCHAR(50) NOT NULL,
                path_info VARCHAR(750) NOT NULL,
                seo_path_info VARCHAR(750) NOT NULL,
                is_canonical INTEGER NULL,
                is_modified INTEGER NOT NULL,
                is_deleted INTEGER NOT NULL
            )
        ');

        $connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'sales_channel_id' => null,
            'language_id' => Uuid::fromHexToBytes($languageId),
            'foreign_key' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'route_name' => 'r',
            'path_info' => '/default',
            'seo_path_info' => 'awesome-product',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
        ]);

        $connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'language_id' => Uuid::fromHexToBytes($languageId),
            'foreign_key' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'route_name' => 'r',
            'path_info' => '/deleted-sales-channel',
            'seo_path_info' => 'awesome-product',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 1,
        ]);

        return $connection;
    }
}
