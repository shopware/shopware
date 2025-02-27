<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\Helper;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Validator as ValidatorInterface;
use PHPUnit\Framework\TestCase;
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
class ValidUserServiceCreator extends TestCase
{
    use KernelTestBehaviour;

    public function __construct()
    {
        parent::__construct('name');
    }

    public function create(): UserService
    {
        $connection = $this->getContainer()->get(Connection::class);

        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(),
            $this->createLoginConfigService(),
            $this->createCache()
        );

        $idTokenParser = new IdTokenParser(
            $publicKeyLoader,
            $this->createLoginConfigService(),
            $this->createMock(ClockInterface::class)
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
        static::assertIsString($jwks);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($jwks);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

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

        return $cache;
    }
}
