<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\AppInfo;
use Shopware\Core\Service\ServiceException;

/**
 * @internal
 */
#[CoversClass(AppInfo::class)]
class AppInfoTest extends TestCase
{
    public static function appInfoProvider(): \Generator
    {
        yield [[]];

        yield [['version' => '1.0.0']];

        yield [['version' => '1.0.0', 'hash' => 'a453f']];

        yield [['hash' => 'a453f']];
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('appInfoProvider')]
    public function testExceptionIsThrownWhenDataIsMissing(array $data): void
    {
        static::expectExceptionObject(ServiceException::missingAppVersionInfo());

        AppInfo::fromNameAndArray('TestApp', $data);
    }

    public function testFromArray(): void
    {
        $appInfo = AppInfo::fromNameAndArray('TestApp', [
            'app-version' => '1.0.0',
            'app-hash' => 'a453f',
            'app-revision' => '1.0.0-a453f',
            'app-zip-url' => 'https://website.com/zip',
            'app-hash-algorithm' => 'xxh128',
            'app-min-shop-supported-version' => '6.7.0.0'
        ]);

        static::assertSame('1.0.0', $appInfo->version);
        static::assertSame('a453f', $appInfo->hash);
        static::assertSame('1.0.0-a453f', $appInfo->revision);
        static::assertSame('https://website.com/zip', $appInfo->zipUrl);
        static::assertSame('xxh128', $appInfo->hashAlgorithm);
        static::assertSame('6.7.0.0', $appInfo->minShopSupportedVersion);
    }

    public function testFromArrayWithNullableFields(): void
    {
        $appInfo = AppInfo::fromNameAndArray('TestApp', [
            'app-version' => '1.0.0',
            'app-hash' => 'a453f',
            'app-revision' => '1.0.0-a453f',
            'app-zip-url' => 'https://website.com/zip'
        ]);

        static::assertEquals('1.0.0', $appInfo->version);
        static::assertEquals('a453f', $appInfo->hash);
        static::assertEquals('1.0.0-a453f', $appInfo->revision);
        static::assertEquals('https://website.com/zip', $appInfo->zipUrl);
        static::assertNull($appInfo->hashAlgorithm);
        static::assertNull($appInfo->minShopSupportedVersion);
    }

    public function testToArray(): void
    {
        $appInfo = new AppInfo('TestApp', '1.0.0', 'a453f', '1.0.0-a453f', 'https://website.com/zip', 'xxh128', '6.7.0.0');

        static::assertSame(
            [
                'version' => '1.0.0',
                'hash' => 'a453f',
                'revision' => '1.0.0-a453f',
                'zip-url' => 'https://website.com/zip',
                'hash-algorithm' => 'xxh128',
                'min-shop-supported-version' => '6.7.0.0'
            ],
            $appInfo->toArray()
        );
    }

    public function testToArrayWithNullableFields(): void
    {
        $appInfo = new AppInfo('TestApp', '1.0.0', 'a453f', '1.0.0-a453f', 'https://website.com/zip');

        static::assertSame(
            [
                'version' => '1.0.0',
                'hash' => 'a453f',
                'revision' => '1.0.0-a453f',
                'zip-url' => 'https://website.com/zip',
                'hash-algorithm' => null,
                'min-shop-supported-version' => null
            ],
            $appInfo->toArray()
        );
    }
}
