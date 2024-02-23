<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('buyers-experience')]
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

    private function getMockConnection(string $salesChannelId, bool $isCanonical, string $pathInfo): Connection&MockObject
    {
        $mock = $this->createMock(Connection::class);
        $firstResult = new Result(new ArrayResult([[
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'isCanonical' => $isCanonical,
            'pathInfo' => $pathInfo,
        ]]), $mock);
        $canonicalResult = new Result(new ArrayResult([[
            'id' => Uuid::randomHex(),
            'isCanonical' => $isCanonical,
            'seoPathInfo' => $pathInfo,
        ]]), $mock);

        $mock
            ->method('executeQuery')
            ->willReturn($firstResult, $canonicalResult);
        $mock
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        return $mock;
    }
}
