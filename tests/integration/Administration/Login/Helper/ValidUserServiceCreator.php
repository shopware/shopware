<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\Helper;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Validator as ValidatorInterface;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\TokenService\IdTokenParser;
use Shopware\Administration\Login\TokenService\PublicKeyLoader;
use Shopware\Administration\Login\UserService\UserService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class ValidUserServiceCreator
{
    use KernelTestBehaviour;

    public function create(): UserService
    {
        $connection = $this->getContainer()->get(Connection::class);

        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(),
            $this->createLoginConfigService(),
            $this->createCache()
        );

        $clockInterface = $this->createMock(ClockInterface::class);
        \assert($clockInterface instanceof ClockInterface);

        $idTokenParser = new IdTokenParser(
            $publicKeyLoader,
            $this->createLoginConfigService(),
            $clockInterface
        );

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(true);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setAccessible(true);
        $validatorProperty->setValue($idTokenParser, $validator);

        return new UserService($connection, $idTokenParser);
    }

    private function createClient(): HttpClientInterface
    {
        $jwks = \file_get_contents(__DIR__ . '/../../../../unit/Administration/Login/TokenService/_fixtures/jwks.json');
        \assert(\is_string($jwks));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($jwks);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        \assert($client instanceof HttpClientInterface);

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
        ];

        return new LoginConfigService($rawConfig, 'local.host', '/admin');
    }

    private function createCache(): AdapterInterface
    {
        $cache = $this->createMock(AdapterInterface::class);
        $createCacheItem = \Closure::bind(
            static function () {
                $item = new CacheItem();
                $item->key = 'cache_key';
                $item->isHit = false;
                $item->value = null;
                $item->unpack();

                return $item;
            },
            null,
            CacheItem::class
        );

        $cacheItem = $createCacheItem();
        $emptyCacheItem = $createCacheItem();

        $cache->method('getItem')->willReturnOnConsecutiveCalls($cacheItem, $emptyCacheItem);

        \assert($cache instanceof AdapterInterface);

        return $cache;
    }

    private function createMock(string $originalClassName): MockObject
    {
        $mock = (new MockGenerator())->testDouble(
            $originalClassName,
            true,
            true,
            callOriginalConstructor: false,
            callOriginalClone: false,
            cloneArguments: false,
            allowMockingUnknownTypes: false,
        );

        \assert($mock instanceof $originalClassName);
        \assert($mock instanceof MockObject);

        return $mock;
    }
}
