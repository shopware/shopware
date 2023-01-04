<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Update\Services\ApiClient
 */
class ApiClientTest extends TestCase
{
    public function testCheckForUpdatesDisabled(): void
    {
        $client = new ApiClient(new MockHttpClient([]), false, __DIR__);
        $version = $client->checkForUpdates();

        static::assertEmpty($version->version);
    }

    public function testCheckForUpdatesUsingEnv(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.4.1.0';

        $client = new ApiClient(new MockHttpClient([]), true, __DIR__);
        $version = $client->checkForUpdates();

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        static::assertSame('6.4.1.0', $version->version);
    }

    public function testCheckUsingClient(): void
    {
        $responses = [
            new MockResponse('["6.4.18.0"]', ['Content-Type' => 'application/json']),
            new MockResponse('{"title": "Shopware", "body": "bla", "date": "2021-09-01", "version": "6.4.8.1", "fixedVulnerabilities": []}', ['Content-Type' => 'application/json']),
        ];

        $client = new ApiClient(new MockHttpClient($responses), true, __DIR__);
        $version = $client->checkForUpdates();

        static::assertSame('6.4.8.1', $version->version);
    }

    public function testDownloadRecoveryToolDoesNothing(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.4.0.0';

        $httpClient = new MockHttpClient([]);
        $client = new ApiClient($httpClient, true, __DIR__);

        $client->downloadRecoveryTool();

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);
        static::assertSame(0, $httpClient->getRequestsCount());
    }

    public function testDownloadRecoveryTool(): void
    {
        $responses = [
            new MockResponse('test', ['Content-Type' => 'application/json']),
        ];

        $fs = new Filesystem();
        $fs->mkdir(__DIR__ . '/public');

        $httpClient = new MockHttpClient($responses);
        $client = new ApiClient($httpClient, true, __DIR__);

        $client->downloadRecoveryTool();

        static::assertFileExists(__DIR__ . '/public/shopware-recovery.phar.php');

        $fs->remove(__DIR__ . '/public');

        static::assertSame(1, $httpClient->getRequestsCount());
    }
}
