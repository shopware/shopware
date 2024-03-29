<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(ApiClient::class)]
class ApiClientTest extends TestCase
{
    public function testCheckForUpdatesDisabled(): void
    {
        $client = new ApiClient(new MockHttpClient([]), false, '6.4.0.0', __DIR__);
        $version = $client->checkForUpdates();

        static::assertEmpty($version->version);
    }

    public function testCheckForUpdatesUsingEnv(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.4.1.0';

        $client = new ApiClient(new MockHttpClient([]), true, '6.4.0.0', __DIR__);
        $version = $client->checkForUpdates();

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        static::assertSame('6.4.1.0', $version->version);
    }

    public function testCheckUsingClient(): void
    {
        $responses = [
            new MockResponse('["6.5.0.0-rc1", "6.4.18.0", "6.4.17.0"]', ['Content-Type' => 'application/json']),
            new MockResponse('{"title": "Shopware", "body": "bla", "date": "2021-09-01", "version": "6.4.8.1", "fixedVulnerabilities": []}', ['Content-Type' => 'application/json']),
        ];

        $client = new ApiClient(new MockHttpClient($responses), true, '6.4.0.0', __DIR__);
        $version = $client->checkForUpdates();

        static::assertSame('6.4.8.1', $version->version);
    }

    public function testUnknownVersion(): void
    {
        $responses = [
            new MockResponse('["6.5.0.0-rc1", "6.4.18.0", "6.4.17.0"]', ['Content-Type' => 'application/json']),
            new MockResponse('{"title": "Shopware", "body": "bla", "date": "2021-09-01", "version": "6.4.8.1", "fixedVulnerabilities": []}', ['Content-Type' => 'application/json']),
        ];

        $client = new ApiClient(new MockHttpClient($responses), true, '6.6.0.0', __DIR__);
        $version = $client->checkForUpdates();

        static::assertSame('6.4.8.1', $version->version);
    }

    public function testMajorUpgrade(): void
    {
        $responses = [
            new MockResponse('["6.5.0.0", "6.4.18.0", "6.4.17.0"]', ['Content-Type' => 'application/json']),
            new MockResponse('{"title": "Shopware", "body": "bla", "date": "2021-09-01", "version": "6.5.0.0", "fixedVulnerabilities": []}', ['Content-Type' => 'application/json']),
        ];

        $client = new ApiClient(new MockHttpClient($responses), true, '6.4.0.0', __DIR__);
        $version = $client->checkForUpdates();

        static::assertSame('6.5.0.0', $version->version);
    }

    public function testNewVersionNotFound(): void
    {
        $responses = [
            new MockResponse('["6.4.17.0"]', ['Content-Type' => 'application/json']),
            new MockResponse('', ['http_code' => 404]),
        ];

        $client = new ApiClient(new MockHttpClient($responses), true, '6.4.0.0', __DIR__);
        $version = $client->checkForUpdates();

        static::assertSame('', $version->version);
        static::assertSame('', $version->body);
        static::assertSame('', $version->title);
    }

    public function testApiDown(): void
    {
        $responses = [
            new MockResponse('["6.4.17.0"]', ['Content-Type' => 'application/json']),
            new MockResponse('', ['http_code' => 500]),
        ];

        $client = new ApiClient(new MockHttpClient($responses), true, '6.4.0.0', __DIR__);

        static::expectException(ServerException::class);
        $client->checkForUpdates();
    }

    public function testDownloadRecoveryToolDoesNothing(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.4.0.0';

        $httpClient = new MockHttpClient([]);
        $client = new ApiClient($httpClient, true, '6.4.0.0', __DIR__);

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
        $client = new ApiClient($httpClient, true, '6.4.0.0', __DIR__);

        $client->downloadRecoveryTool();

        static::assertFileExists(__DIR__ . '/public/shopware-installer.phar.php');

        $fs->remove(__DIR__ . '/public');

        static::assertSame(1, $httpClient->getRequestsCount());
    }
}
