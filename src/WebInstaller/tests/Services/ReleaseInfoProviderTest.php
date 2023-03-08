<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\ReleaseInfoProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @covers \App\Services\ReleaseInfoProvider
 */
class ReleaseInfoProviderTest extends TestCase
{
    public function testGetReleaseInfo(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse(json_encode([
                '6.5.0.0-rc1',
                '6.4.12.0',
                '6.4.11.0',
                '6.3.5.0',
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $releaseInfoProvider = new ReleaseInfoProvider($mockClient);

        $releaseInfo = $releaseInfoProvider->fetchLatestReleaseForUpdate();

        static::assertArrayHasKey('6.3', $releaseInfo);
        static::assertArrayHasKey('6.4', $releaseInfo);
        static::assertSame('6.4.12.0', $releaseInfo['6.4']);
        static::assertSame('6.3.5.0', $releaseInfo['6.3']);
    }

    public function testGetReleaseInfoIncludeRC(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse(json_encode([
                '6.5.0.0-rc1',
                '6.4.12.0',
                '6.4.11.0',
                '6.3.5.0',
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $releaseInfoProvider = new ReleaseInfoProvider($mockClient);

        $releaseInfo = $releaseInfoProvider->fetchLatestReleaseForUpdate(true);

        static::assertArrayHasKey('6.3', $releaseInfo);
        static::assertArrayHasKey('6.4', $releaseInfo);
        static::assertArrayHasKey('6.5', $releaseInfo);
        static::assertSame('6.5.0.0-rc1', $releaseInfo['6.5']);
        static::assertSame('6.4.12.0', $releaseInfo['6.4']);
        static::assertSame('6.3.5.0', $releaseInfo['6.3']);
    }

    public function testGetReleaseInfoWithNextVersion(): void
    {
        $releaseInfoProvider = new ReleaseInfoProvider();

        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.99.9';

        $releaseInfo = $releaseInfoProvider->fetchLatestReleaseForUpdate();

        static::assertArrayHasKey('6.4', $releaseInfo);
        static::assertArrayHasKey('6.5', $releaseInfo);

        static::assertSame('6.4.17.2', $releaseInfo['6.4']);
        static::assertSame('6.5.99.9', $releaseInfo['6.5']);

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);
    }

    public function testGetReleaseVersions(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse(json_encode([
                '6.5.0.0-rc1',
                '6.4.19.0',
                '6.4.18.0',
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                '6.5.0.0-rc1',
                '6.4.19.0',
                '6.4.18.0',
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $releaseInfoProvider = new ReleaseInfoProvider($mockClient);

        static::assertSame(
            [
                '6.4.19.0',
                '6.4.18.0',
            ],
            $releaseInfoProvider->fetchInstallVersions()
        );

        static::assertSame(
            [
                '6.5.0.0-rc1',
                '6.4.19.0',
                '6.4.18.0',
            ],
            $releaseInfoProvider->fetchInstallVersions(true)
        );
    }
}
