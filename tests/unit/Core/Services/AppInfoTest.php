<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\AppInfo;
use Shopware\Core\Services\ServicesException;

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
        static::expectExceptionObject(ServicesException::missingAppVersionInfo());

        AppInfo::fromNameAndArray('TestApp', $data);
    }

    public function testFromArray(): void
    {
        $appInfo = AppInfo::fromNameAndArray('TestApp', ['app-version' => '1.0.0', 'app-hash' => 'a453f', 'app-revision' => '1.0.0-a453f', 'app-zip-url' => 'https://website.com/zip']);

        static::assertEquals('1.0.0', $appInfo->version);
        static::assertEquals('a453f', $appInfo->hash);
        static::assertEquals('1.0.0-a453f', $appInfo->revision);
        static::assertEquals('https://website.com/zip', $appInfo->zipUrl);
    }

    public function testToArray(): void
    {
        $appInfo = new AppInfo('TestApp', '1.0.0', 'a453f', '1.0.0-a453f', 'https://website.com/zip');

        static::assertEquals(
            ['version' => '1.0.0', 'hash' => 'a453f', 'revision' => '1.0.0-a453f', 'zip-url' => 'https://website.com/zip'],
            $appInfo->toArray()
        );
    }
}
