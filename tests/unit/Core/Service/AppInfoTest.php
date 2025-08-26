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

        yield [['app-version' => '1.0.0', 'app-hash' => 'a453f', 'app-revision' => '1.0.0-a453f', 'app-zip-url' => 'https://example.com/zip']];

        yield [['app-version' => '1.0.0', 'app-hash' => 'a453f', 'app-revision' => '1.0.0-a453f', 'app-zip-url' => 'https://example.com/zip', 'app-hash-algorithm' => 'sha256']];

        yield [['app-version' => '1.0.0', 'app-hash' => 'a453f', 'app-revision' => '1.0.0-a453f', 'app-zip-url' => 'https://example.com/zip', 'app-min-shop-supported-version' => '6.6.0.0']];
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('appInfoProvider')]
    public function testExceptionIsThrownWhenDataIsMissing(array $data): void
    {
        static::expectException(ServiceException::class);
        static::expectExceptionMessage('Error downloading app. The version information was missing:');

        AppInfo::fromNameAndArray('TestApp', $data);
    }

    public function testFromArrayWithAllFields(): void
    {
        $appInfo = AppInfo::fromNameAndArray('TestApp', [
            'app-version' => '1.0.0',
            'app-hash' => 'a453f',
            'app-revision' => '1.0.0-a453f',
            'app-zip-url' => 'https://example.com/zip',
            'app-hash-algorithm' => 'sha256',
            'app-min-shop-supported-version' => '6.6.0.0',
        ]);

        static::assertSame('TestApp', $appInfo->name);
        static::assertSame('1.0.0', $appInfo->version);
        static::assertSame('a453f', $appInfo->hash);
        static::assertSame('1.0.0-a453f', $appInfo->revision);
        static::assertSame('https://example.com/zip', $appInfo->zipUrl);
        static::assertSame('sha256', $appInfo->hashAlgorithm);
        static::assertSame('6.6.0.0', $appInfo->minShopSupportedVersion);
    }

    public function testToArray(): void
    {
        $appInfo = new AppInfo('TestApp', '1.0.0', 'a453f', '1.0.0-a453f', 'https://example.com/zip', 'sha256', '6.6.0.0');

        static::assertSame(
            [
                'version' => '1.0.0',
                'hash' => 'a453f',
                'revision' => '1.0.0-a453f',
                'zip-url' => 'https://example.com/zip',
                'hash-algorithm' => 'sha256',
                'min-shop-supported-version' => '6.6.0.0',
            ],
            $appInfo->toArray()
        );
    }
}
