<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Store\Services\OpenSSLVerifier;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ApiClientCacheTest extends TestCase
{
    // NEXT-15013 is only reproducible with filesystem cache adapter
    // We've removed the cache altogether
    /*
    private FilesystemAdapter $cacheWithoutPlugins;
    private FilesystemAdapter $cacheWithPlugins;

    public function setUp(): void
    {
        $cacheDirWithoutPlugins = sys_get_temp_dir() . '/' . uniqid('cacheTest');
        $this->cacheWithoutPlugins = new FilesystemAdapter(
            '',
            0,
            $cacheDirWithoutPlugins . '/pools',
            new DefaultMarshaller(null)
        );
        $this->cacheWithoutPlugins->setLogger(new NullLogger());

        $cacheDirWithPlugins = sys_get_temp_dir() . '/' . uniqid('cacheTest');
        $this->cacheWithPlugins = new FilesystemAdapter(
            '',
            0,
            $cacheDirWithPlugins . '/pools',
            new DefaultMarshaller(null)
        );
        $this->cacheWithPlugins->setLogger(new NullLogger());
    }
    */

    public function testCaching(): void
    {
        $oldVersionClient = $this->getClient('6.4.1.0');
        $newVersionClient = $this->getClient('6.5.0.0');

        // cache without plugins and old client
        $apiClientWithoutPlugins = new ApiClient(
            '6.4.0.0',
            // $this->cacheWithoutPlugins,
            $this->createMock(SystemConfigService::class),
            $this->createMock(OpenSSLVerifier::class),
            $oldVersionClient,
            true
        );
        $version = $apiClientWithoutPlugins->checkForUpdates();
        static::assertSame('6.4.1.0', $version->version);

        // cache with plugins and new version client
        $apiClientWithPlugins = new ApiClient(
            '6.4.0.0',
            // $this->cacheWithPlugins,
            $this->createMock(SystemConfigService::class),
            $this->createMock(OpenSSLVerifier::class),
            $newVersionClient,
            true
        );

        $version = $apiClientWithPlugins->checkForUpdates();
        static::assertSame('6.5.0.0', $version->version);

        // old cache with new version client should not return stale data
        $test = new ApiClient(
            '6.4.0.0',
            // $this->cacheWithoutPlugins,
            $this->createMock(SystemConfigService::class),
            $this->createMock(OpenSSLVerifier::class),
            $newVersionClient,
            true
        );

        // read stale data?
        $version = $test->checkForUpdates();
        static::assertSame('6.5.0.0', $version->version);
    }

    private function getClient(string $version): Client
    {
        $client = $this->createMock(Client::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeader')
            ->with('x-shopware-signature')
            ->willReturn('withoutPlugin');

        $response->method('getBody')
            ->willReturn(json_encode([
                'version' => $version,
                'release_date' => null,
                'security_update' => false,
                'uri' => 'https://releases.shopware.com/sw6/update_' . $version . '.zip',
                'size' => '10300647',
                'sha1' => '989a66605d12d347ceb727c73954bb0ba3b9192d',
                'sha256' => '8541ba418536bc84b1cd90063a3a41240646cbf83eef0fe809a0b02977e623c4',
                'isNewer' => true,
            ]));

        $client->method('get')->willReturn($response);

        return $client;
    }
}
