<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso;

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
use Shopware\Core\Framework\Sso\ShopwarePasswordGrantType;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeUserInstaller;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwarePasswordGrantType::class)]
class ShopwarePasswordGrantTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRespondToAccessTokenWithoutOAuthUser(): void
    {
        $request = $this->createRequest();

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
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');
    }

    public function testRespondToAccessTokenWithOAuthUser(): void
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

        $request = $this->createRequest();

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

    public function createRequest(): Request
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
}
