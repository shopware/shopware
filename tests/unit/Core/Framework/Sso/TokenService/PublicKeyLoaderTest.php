<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\TokenService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PublicKeyLoader::class)]
class PublicKeyLoaderTest extends TestCase
{
    public function testLoadPublicKey(): void
    {
        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(true, $this->getJwks()),
            $this->createLoginConfigService(),
            $this->createCache()
        );

        $publicKey = $publicKeyLoader->loadPublicKey(JwksIds::KEY_ID_ONE);

        $result = $publicKey->contents();

        static::assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $result);
        static::assertStringContainsString('MC8wDQYJKoZIhvcNAQEBBQADHgAwGwIVdGhpcyBpcyBhIHNpbXBsZSB0ZXN0AgIQ', $result);
        static::assertStringEndsWith('-----END PUBLIC KEY-----', $result);
    }

    public function testLoadPublicKeyShouldComeFromCache(): void
    {
        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(false, ''),
            $this->createLoginConfigService(),
            $this->createCache($this->getJwks())
        );

        $publicKey = $publicKeyLoader->loadPublicKey(JwksIds::KEY_ID_TWO);

        $result = $publicKey->contents();

        static::assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $result);
        static::assertStringContainsString('MCkwDQYJKoZIhvcNAQEBBQADGAAwFQIPYXNkYXNkYXNkYXNkYXNkAgIQgw==', $result);
        static::assertStringEndsWith('-----END PUBLIC KEY-----', $result);
    }

    public function testLoadPublicKeyShouldUpdateCache(): void
    {
        $cachedKeys = \json_decode($this->getJwks(), true);
        $cachedKeys['keys'][0]['kid'] = Uuid::randomHex();
        $cachedKeys['keys'][1]['kid'] = Uuid::randomHex();

        $cachedKeys = \json_encode($cachedKeys);
        static::assertIsString($cachedKeys);

        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(true, $this->getJwks()),
            $this->createLoginConfigService(),
            $this->createCache($cachedKeys)
        );

        $publicKey = $publicKeyLoader->loadPublicKey(JwksIds::KEY_ID_ONE, true);

        $result = $publicKey->contents();

        static::assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $result);
        static::assertStringContainsString('MC8wDQYJKoZIhvcNAQEBBQADHgAwGwIVdGhpcyBpcyBhIHNpbXBsZSB0ZXN0AgIQ', $result);
        static::assertStringEndsWith('-----END PUBLIC KEY-----', $result);
    }

    public function testLoadPublicKeyShouldThrowException(): void
    {
        $cachedKeys = \json_decode($this->getJwks(), true);
        $cachedKeys['keys'][0]['kid'] = Uuid::randomHex();
        $cachedKeys['keys'][1]['kid'] = Uuid::randomHex();

        $cachedKeys = \json_encode($cachedKeys);
        static::assertIsString($cachedKeys);

        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(false, $cachedKeys),
            $this->createLoginConfigService(),
            $this->createCache($cachedKeys)
        );

        try {
            $publicKeyLoader->loadPublicKey(JwksIds::KEY_ID_TWO);
        } catch (SsoException $loginException) {
            static::assertSame('Public key not found', $loginException->getMessage());
            static::assertSame('SSO_LOGIN__PUBLIC_KEY_NOT_FOUND', $loginException->getErrorCode());
            static::assertSame(Response::HTTP_UNAUTHORIZED, $loginException->getStatusCode());

            return;
        }

        static::fail('An exception should have been thrown.');
    }

    private function createClient(bool $shouldBeCalled, string $data): HttpClientInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($data);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($shouldBeCalled ? $this->once() : $this->never())->method('request')->willReturn($response);

        return $client;
    }

    private function createLoginConfigService(): LoginConfigService
    {
        $rawConfig = [
            'use_default' => false,
            'client_id' => 'c6a7ab8a-5c0c-4353-a38a-1b42479ef090',
            'client_secret' => '42fec3f9-a19b-4796-bce9-cb395a28da9f',
            'redirect_uri' => 'https://redirect.to',
            'base_url' => 'https://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
        ];

        return new LoginConfigService($rawConfig, $this->createMock(RouterInterface::class));
    }

    private function createCache(?string $cached = null): AdapterInterface&CacheInterface
    {
        $cache = new ArrayAdapter();

        if ($cached !== null) {
            $createCacheItem = \Closure::bind(
                static function ($cached) {
                    $item = new CacheItem();
                    $item->key = 'admin_sso_public_key_storage';
                    $item->isHit = true;
                    $item->value = $cached;
                    $item->unpack();

                    return $item;
                },
                null,
                CacheItem::class
            );

            $cacheItem = $createCacheItem($cached);
            $cache->save($cacheItem);
        }

        return $cache;
    }

    private function getJwks(): string
    {
        $jwks = file_get_contents(__DIR__ . '/_fixtures/jwks.json');

        static::assertIsString($jwks);

        return $jwks;
    }
}
