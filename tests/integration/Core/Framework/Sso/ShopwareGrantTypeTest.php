<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\OAuth\ScopeRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\ShopwareGrantType;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeUserInstaller;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\ValidUserServiceCreator;
use Shopware\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
class ShopwareGrantTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->userService = (new ValidUserServiceCreator('create'))->create(
            $this->connection,
            static::getContainer()->get('user.repository'),
        );
    }

    public function testRespondToAccessTokenRequest(): void
    {
        $email = 'test@shopware.com';
        $idToken = (new FakeTokenGenerator())->setEmail($email)->generate(JwksIds::KEY_ID_ONE);

        $fakeUserInstall = new FakeUserInstaller($this->connection);
        $fakeUserInstall->installBaseUserData(Uuid::randomHex(), $email);

        $session = new Session(new MockArraySessionStorage());
        $session->set('sso_proof_key_verifier', 'proofKeyVerifier');

        $shopwareGrantType = new ShopwareGrantType(
            new RefreshTokenRepository($this->connection, new NativeClock()),
            $this->userService,
            $this->createExternalTokenService($idToken),
            new NativeClock()
        );

        $shopwareGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $shopwareGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $shopwareGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $shopwareGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $shopwareGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $shopwareGrantType->setDefaultScope('');

        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('code', 'code');

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);
        $ttl = new \DateInterval('PT1H');

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $responseResult = $shopwareGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        static::assertInstanceOf(BearerTokenResponse::class, $responseResult);
        $result = $responseResult->generateHttpResponse(new Psr7Response());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);

        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame('Bearer', $responseBodyData['token_type']);
        // Assert that expires_in is between 3595 and 3600 to account for minor delays in processing
        static::assertTrue($responseBodyData['expires_in'] <= 3600 && $responseBodyData['expires_in'] > 3595);
        static::assertIsString($responseBodyData['access_token']);
        static::assertIsString($responseBodyData['refresh_token']);
    }

    private function createExternalTokenService(string $token): ExternalTokenService
    {
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

        $loginConfig = new LoginConfigService(
            [
                'use_default' => false,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'http://redirect.uri',
                'base_url' => 'http://base.uri',
                'session_key' => 'session_key',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            static::createStub(RouterInterface::class)
        );

        return new ExternalTokenService($client, $loginConfig);
    }
}
