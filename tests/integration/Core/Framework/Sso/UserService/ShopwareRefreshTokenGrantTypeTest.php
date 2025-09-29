<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso\Login;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\OAuth\ScopeRepository;
use Shopware\Core\Framework\Api\OAuth\UserRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\ShopwarePasswordGrantType;
use Shopware\Core\Framework\Sso\ShopwareRefreshTokenGrantType;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeUserInstaller;
use Shopware\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareRefreshTokenGrantType::class)]
class ShopwareRefreshTokenGrantTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRespondToAccessTokenRequestWithoutOAuthUser(): void
    {
        $refreshToken = $this->createRefreshToken();
        $request = $this->createRefreshGrantRequest($refreshToken);
        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');

        $shopwareRefreshTokenGrantType = $this->createShopwareRefreshTokenGrantType();

        $response = $shopwareRefreshTokenGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');
    }

    public function testRespondToAccessTokenRequestWithOAuthUser(): void
    {
        $refreshToken = $this->createRefreshToken();
        $request = $this->createRefreshGrantRequest($refreshToken);

        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::fromBytesToHex(
            $connection->createQueryBuilder()
                ->select('id')
                ->from('user')
                ->where('username = :username')
                ->setParameter('username', 'admin')
                ->executeQuery()
                ->fetchOne()
        );

        (new FakeUserInstaller($connection))->installTokenUser($userId, Uuid::randomHex());

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');

        $shopwareRefreshTokenGrantType = $this->createShopwareRefreshTokenGrantType();

        $response = $shopwareRefreshTokenGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');

        $tokenResult = $connection->createQueryBuilder()
            ->select('token')
            ->from('oauth_user')
            ->where('user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchOne();

        static::assertIsString($tokenResult);
        $jsonResult = \json_decode($tokenResult, true);
        static::assertIsArray($jsonResult);
        static::assertArrayHasKey('token', $jsonResult);
        static::assertArrayHasKey('refreshToken', $jsonResult);

        // expect updated oauth_user.token
        static::assertSame('new_access_token', $jsonResult['token']);
        static::assertSame('new_refresh_token', $jsonResult['refreshToken']);
    }

    public function testRespondToAccessTokenRequestWithOAuthUserWithNullToken(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::fromBytesToHex(
            $connection->createQueryBuilder()
                ->select('id')
                ->from('user')
                ->where('username = :username')
                ->setParameter('username', 'admin')
                ->executeQuery()
                ->fetchOne()
        );

        (new FakeUserInstaller($connection))->installTokenUser($userId, Uuid::randomHex());

        $refreshToken = $this->createRefreshToken();
        $request = $this->createRefreshGrantRequest($refreshToken);

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');

        $shopwareRefreshTokenGrantType = $this->createShopwareRefreshTokenGrantType();

        $response = $shopwareRefreshTokenGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');

        $tokenResult = $connection->createQueryBuilder()
            ->select('token')
            ->from('oauth_user')
            ->where('user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchOne();

        static::assertNull($tokenResult);
    }

    private function createShopwareRefreshTokenGrantType(): ShopwareRefreshTokenGrantType
    {
        $shopwareRefreshTokenGrantType = new ShopwareRefreshTokenGrantType(
            $this->getContainer()->get(RefreshTokenRepository::class),
            $this->getContainer()->get(UserService::class),
            $this->createExternalTokenService()
        );

        $shopwareRefreshTokenGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $shopwareRefreshTokenGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $shopwareRefreshTokenGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $shopwareRefreshTokenGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $shopwareRefreshTokenGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $shopwareRefreshTokenGrantType->setDefaultScope('');
        $shopwareRefreshTokenGrantType->setEncryptionKey('key');

        return $shopwareRefreshTokenGrantType;
    }

    private function createRefreshToken(): string
    {
        $request = $this->createPasswordGrantRequest();

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');
        $shopwarePasswordGrantType = $this->createShopwarePasswortGrantType();

        $response = $shopwarePasswordGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);

        return $responseBodyData['refresh_token'];
    }

    private function createPasswordGrantRequest(): Request
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', 'password');
        $request->request->set('scope', 'write');
        $request->request->set('username', 'admin');
        $request->request->set('password', 'shopware');

        return $request;
    }

    private function createRefreshGrantRequest(string $refreshToken): Request
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', 'refresh_token');
        $request->request->set('scope', 'write');
        $request->request->set('refresh_token', $refreshToken);

        return $request;
    }

    private function createShopwarePasswortGrantType(): ShopwarePasswordGrantType
    {
        $shopwarePasswordGrantType = new ShopwarePasswordGrantType(
            $this->getContainer()->get(UserRepository::class),
            new RefreshTokenRepository($this->getContainer()->get(Connection::class)),
            $this->getContainer()->get(UserService::class)
        );

        $shopwarePasswordGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $shopwarePasswordGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $shopwarePasswordGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $shopwarePasswordGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $shopwarePasswordGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $shopwarePasswordGrantType->setDefaultScope('');

        return $shopwarePasswordGrantType;
    }

    private function createExternalTokenService(): ExternalTokenService
    {
        $idToken = (new FakeTokenGenerator())->setEmail('user@example.com')->generate(JwksIds::KEY_ID_ONE);

        $responseInterface = $this->createMock(ResponseInterface::class);
        $responseInterface->method('getContent')->willReturn(
            \json_encode(
                [
                    'id_token' => $idToken,
                    'access_token' => 'new_access_token',
                    'refresh_token' => 'new_refresh_token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                    'scope' => 'scope',
                ]
            )
        );

        $client = $this->createMock(HttpClientInterface::class);
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
            $this->createMock(RouterInterface::class)
        );

        return new ExternalTokenService($client, $loginConfig);
    }
}
