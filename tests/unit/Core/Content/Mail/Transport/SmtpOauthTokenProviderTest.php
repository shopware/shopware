<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Content\Mail\Transport\SmtpOauthTokenProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(SmtpOauthTokenProvider::class)]
class SmtpOauthTokenProviderTest extends TestCase
{
    public function testGetTokenFetchesFromCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $configService = $this->createMock(SystemConfigService::class);

        $cache->expects(static::once())
            ->method('get')
            ->with('email-token')
            ->willReturn('cached-token');

        $provider = new SmtpOauthTokenProvider($httpClient, $cache, $configService);

        $token = $provider->getToken();

        static::assertSame('cached-token', $token);
    }

    public function testGetTokenFetchesFromApiIfNotInCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $configService = $this->createMock(SystemConfigService::class);
        $cacheItem = $this->createMock(ItemInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $cache->expects(static::once())
            ->method('get')
            ->with('email-token')
            ->willReturnCallback(function ($_, callable $callback) use ($cacheItem) {
                return $callback($cacheItem);
            });

        $configService
            ->method('getString')
            ->willReturnMap([
                ['core.mailerSettings.clientId', null, 'test-client-id'],
                ['core.mailerSettings.clientSecret', null, 'test-client-secret'],
                ['core.mailerSettings.oauthScope', null, 'test-scope'],
                ['core.mailerSettings.oauthUrl', null, 'https://oauth.example.com/token'],
            ]);

        $httpClient->expects(static::once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth.example.com/token',
                [
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'body' => http_build_query([
                        'client_id' => 'test-client-id',
                        'client_secret' => 'test-client-secret',
                        'scope' => 'test-scope',
                        'grant_type' => 'client_credentials',
                    ]),
                ]
            )
            ->willReturn($response);

        $response->expects(static::once())
            ->method('getStatusCode')
            ->willReturn(Response::HTTP_OK);

        $response->expects(static::once())
            ->method('toArray')
            ->willReturn(['access_token' => 'new-token', 'expires_in' => 3600]);

        $cacheItem->expects(static::once())
            ->method('expiresAfter')
            ->with(3540); // 3600 - 60 seconds

        $provider = new SmtpOauthTokenProvider($httpClient, $cache, $configService);

        $token = $provider->getToken();

        static::assertSame('new-token', $token);
    }

    public function testFetchTokenThrowsExceptionOnHttpError(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $configService = $this->createMock(SystemConfigService::class);
        $cacheItem = $this->createMock(ItemInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $cache->expects(static::once())
            ->method('get')
            ->with('email-token')
            ->willReturnCallback(function ($_, callable $callback) use ($cacheItem) {
                return $callback($cacheItem);
            });

        $configService
            ->method('getString')
            ->willReturnMap([
                ['core.mailerSettings.clientId', null, 'test-client-id'],
                ['core.mailerSettings.clientSecret', null, 'test-client-secret'],
                ['core.mailerSettings.oauthScope', null, 'test-scope'],
                ['core.mailerSettings.oauthUrl', null, 'https://oauth.example.com/token'],
            ]);

        $httpClient->expects(static::once())
            ->method('request')
            ->willReturn($response);

        $response->expects(static::once())
            ->method('getStatusCode')
            ->willReturn(Response::HTTP_BAD_REQUEST);

        $response->expects(static::once())
            ->method('getContent')
            ->willReturn('Error details');

        $provider = new SmtpOauthTokenProvider($httpClient, $cache, $configService);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Failed to fetch oauth token: Error details');

        $provider->getToken();
    }
}
