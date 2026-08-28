<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso\Helper;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Validator as ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\System\User\UserCollection;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
class ValidUserServiceCreator extends TestCase
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function create(Connection $connection, EntityRepository $userRepository): UserService
    {
        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(),
            $this->createLoginConfigService(),
            new ArrayAdapter()
        );

        $idTokenParser = new IdTokenParser(
            $publicKeyLoader,
            $this->createLoginConfigService(),
            static::createStub(ClockInterface::class)
        );

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(true);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setValue($idTokenParser, $validator);

        return new UserService($connection, $idTokenParser, $userRepository, $this->createExternalTokenService(), new NativeClock());
    }

    private function createClient(): HttpClientInterface
    {
        $filesystem = new Filesystem();
        $jwks = $filesystem->readFile(__DIR__ . '/../../../../../unit/Core/Framework/Sso/TokenService/_fixtures/jwks.json');

        $response = static::createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn($jwks);

        $client = static::createStub(HttpClientInterface::class);
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
            'register_url' => 'https://register.url',
        ];

        return new LoginConfigService($rawConfig, static::createStub(RouterInterface::class));
    }

    private function createExternalTokenService(): ExternalTokenService
    {
        $token = (new FakeTokenGenerator())->generate();
        $responseInterface = static::createStub(ResponseInterface::class);
        $responseInterface->method('getContent')->willReturn(
            \json_encode(
                [
                    'id_token' => $token,
                    'access_token' => 'access_token',
                    'refresh_token' => 'refresh_token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                    'scope' => 'scope',
                ]
            )
        );

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($responseInterface);

        $loginConfigService = $this->createLoginConfigService();

        return new ExternalTokenService($client, $loginConfigService);
    }
}
