<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\ReleaseInfoProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(ReleaseInfoProvider::class)]
class ReleaseInfoProviderTest extends TestCase
{
    public function testGetReleaseInfo(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse(json_encode([
                '6.5.0.0-rc1',
                '6.4.20.0',
                '6.4.19.0',
                '6.4.18.0',
                '6.4.12.0',
                '6.4.11.0',
                '6.3.5.0',
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $releaseInfoProvider = new ReleaseInfoProvider($mockClient);

        $releaseInfo = $releaseInfoProvider->fetchUpdateVersions('6.4.0.0');

        static::assertSame(
            [
                '6.5.0.0-rc1',
                '6.4.20.0',
                '6.4.19.0',
                '6.4.18.0',
            ],
            $releaseInfo
        );
    }

    public function testFetchVersions(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse(json_encode([
                '6.4.19.0',
                '6.5.0.0-rc1',
                '6.4.18.0',
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $releaseInfoProvider = new ReleaseInfoProvider($mockClient);

        static::assertSame(
            [
                '6.5.0.0-rc1',
                '6.4.19.0',
                '6.4.18.0',
            ],
            $releaseInfoProvider->fetchVersions()
        );
    }

    public function testForcingVersion(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '1.2.3';

        $mockClient = new MockHttpClient([]);

        $releaseInfoProvider = new ReleaseInfoProvider($mockClient);

        static::assertSame(
            [
                '1.2.3',
            ],
            $releaseInfoProvider->fetchUpdateVersions('1.2.3')
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);
    }
}
