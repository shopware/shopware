<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Doctrine\FakeResultFactory;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoResolver::class)]
class SeoResolverTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function resolveDataProvider(): array
    {
        return [
            'null case' => [
                '',
                '/',
            ],
            'same content, leading, but trailing slash' => [
                '/seo-url',
                '/seo-url',
            ],
            'same content, leading and trailing slash' => [
                '/seo-url/',
                '/seo-url/',
            ],
            'no trailing slash' => [
                'seo-url',
                '/seo-url',
            ],
            'trailing slash' => [
                'seo-url/',
                '/seo-url/',
            ],
            '2 levels, no trailing slash' => [
                'seo-url/nice-addition',
                '/seo-url/nice-addition',
            ],
            '2 levels, trailing slash' => [
                'seo-url/nice-addition/',
                '/seo-url/nice-addition/',
            ],
            'lots of levels, no trailing slash' => [
                'seo-url/nice-addition/with/something/really/really/reaaaaally/long',
                '/seo-url/nice-addition/with/something/really/really/reaaaaally/long',
            ],
            'lots of levels, trailing slash' => [
                'seo-url/nice-addition/with/something/really/really/reaaaaally/long/',
                '/seo-url/nice-addition/with/something/really/really/reaaaaally/long/',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function resolveCanonicalDataProvider(): array
    {
        return [
            'null case' => [
                '',
                '/',
            ],
            'same content, leading, but trailing slash' => [
                '/Industrial-Kids',
                '/Industrial-Kids',
            ],
            'same content, leading and trailing slash' => [
                '/Industrial-Kids/',
                '/Industrial-Kids/',
            ],
            'no trailing slash' => [
                'Industrial-Kids',
                '/Industrial-Kids',
            ],
            'trailing slash' => [
                'Industrial-Kids/',
                '/Industrial-Kids/',
            ],
            'lots of levels, no trailing slash' => [
                'Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books',
                '/Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books',
            ],
            'lots of levels, trailing slash' => [
                'Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books/',
                '/Industrial-Kids/Automotive/Outdoors-Books-Beauty/Shoes-Beauty-Books/',
            ],
        ];
    }

    #[DataProvider('resolveDataProvider')]
    public function testResolveWithIsCanonical(string $pathInfo, string $expected): void
    {
        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->getMockConnection($salesChannelId, true, $pathInfo));

        $resolvedSeoUrl = $seoResolver->resolve(Uuid::randomHex(), $salesChannelId, $pathInfo);

        static::assertSame($expected, $resolvedSeoUrl['pathInfo']);
    }

    #[DataProvider('resolveCanonicalDataProvider')]
    public function testResolveWithNotCanonical(string $pathInfo, string $expected): void
    {
        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->getMockConnection($salesChannelId, false, $pathInfo));

        /** @var array{canonicalPathInfo: string, pathInfo: string, isCanonical: bool} $resolvedSeoUrl */
        $resolvedSeoUrl = $seoResolver->resolve(Uuid::randomHex(), $salesChannelId, $pathInfo);

        static::assertSame($expected, $resolvedSeoUrl['canonicalPathInfo']);
    }

    public function testResolveIgnoresDeletedSeoUrls(): void
    {
        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $seoResolver = new SeoResolver($this->createSqliteConnectionWithDeletedSeoUrl($languageId, $salesChannelId));

        /** @var array{pathInfo: string, isCanonical: bool|string} $resolvedSeoUrl */
        $resolvedSeoUrl = $seoResolver->resolve($languageId, $salesChannelId, 'awesome-product');

        static::assertSame('/default', $resolvedSeoUrl['pathInfo']);
        static::assertTrue((bool) $resolvedSeoUrl['isCanonical']);
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
