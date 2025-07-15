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
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
            new ArrayAdapter()
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

        $userRepository = $this->getContainer()->get('user.repository');

        return new UserService($connection, $idTokenParser, $userRepository);
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
            'register_url' => 'https://register.url',
        ];

        return new LoginConfigService($rawConfig, 'local.host', '/admin');
    }

    private function createMock(string $originalClassName): MockObject
    {
        $mock = (new MockGenerator())->testDouble(
            $originalClassName,
            true,
            true,
            [],
            [],
            '',
            false,
            false,
            true,
            false,
            false,
            null,
            false
        );

        \assert($mock instanceof $originalClassName);
        \assert($mock instanceof MockObject);

        return $mock;
    }
}
