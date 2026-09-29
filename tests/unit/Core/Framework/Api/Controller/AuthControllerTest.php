<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Controller\AuthController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AuthController::class)]
class AuthControllerTest extends TestCase
{
    public function testRateLimiterIsCalledWithCorrectKeys(): void
    {
        $username = 'Admin';
        $ip = '10.0.0.1';
        $expectedUsernameKey = strtolower($username);
        $expectedCombinedKey = $expectedUsernameKey . '-' . $ip;

        $ensureAcceptedCalls = [];
        $ensureIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use (&$ensureAcceptedCalls): void {
                $ensureAcceptedCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('ensureAcceptedIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$ensureIfConfiguredCalls): void {
                $ensureIfConfiguredCalls[] = [$route, $key];
            });

        $response = new Response();
        $authServer = $this->createMock(AuthorizationServer::class);
        $authServer->expects($this->once())->method('respondToAccessTokenRequest')->willReturn($response);

        $psrFactory = $this->createMock(PsrHttpFactory::class);
        $psrFactory->expects($this->once())->method('createRequest')->willReturn(new ServerRequest('POST', '/api/oauth/token'));
        $psrFactory->expects($this->once())->method('createResponse')->willReturn($response);

        $controller = new AuthController($authServer, $psrFactory, $rateLimiter, static::createStub(Connection::class));

        $request = new Request(server: ['REMOTE_ADDR' => $ip]);
        $request->request->set('username', $username);

        $controller->token($request);

        static::assertSame([[RateLimiter::OAUTH, $expectedCombinedKey]], $ensureAcceptedCalls);
        static::assertSame([
            [RateLimiter::OAUTH_USER, $expectedUsernameKey],
            [RateLimiter::OAUTH_CLIENT, $ip],
        ], $ensureIfConfiguredCalls);
    }

    public function testRateLimitersAreResetOnSuccess(): void
    {
        $username = 'admin';
        $ip = '10.0.0.1';
        $combinedKey = $username . '-' . $ip;

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $response = new Response();
        $authServer = $this->createMock(AuthorizationServer::class);
        $authServer->expects($this->once())->method('respondToAccessTokenRequest')->willReturn($response);

        $psrFactory = $this->createMock(PsrHttpFactory::class);
        $psrFactory->expects($this->once())->method('createRequest')->willReturn(new ServerRequest('POST', '/api/oauth/token'));
        $psrFactory->expects($this->once())->method('createResponse')->willReturn($response);

        $controller = new AuthController($authServer, $psrFactory, $rateLimiter, static::createStub(Connection::class));

        $request = new Request(server: ['REMOTE_ADDR' => $ip]);
        $request->request->set('username', $username);

        $controller->token($request);

        static::assertSame([[RateLimiter::OAUTH, $combinedKey]], $resetCalls);
        static::assertSame([
            [RateLimiter::OAUTH_USER, $username],
            [RateLimiter::OAUTH_CLIENT, $ip],
        ], $resetIfConfiguredCalls);
    }

    public function testRateLimitThrowsApiException(): void
    {
        $rateLimiter = static::createStub(RateLimiter::class);
        $rateLimiter->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(time() + 60));

        $controller = new AuthController(
            static::createStub(AuthorizationServer::class),
            static::createStub(PsrHttpFactory::class),
            $rateLimiter,
            static::createStub(Connection::class),
        );

        $this->expectException(ApiException::class);

        $request = new Request(server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->request->set('username', 'admin');
        $controller->token($request);
    }

    #[DataProvider('transactionalGrantTypes')]
    public function testCodeAndRefreshGrantsCommitAtomically(string $grantType): void
    {
        $response = new Response();
        $authServer = $this->createMock(AuthorizationServer::class);
        $authServer->expects($this->once())->method('respondToAccessTokenRequest')->willReturn($response);

        $psrFactory = static::createStub(PsrHttpFactory::class);
        $psrFactory->method('createRequest')->willReturn(new ServerRequest('POST', '/api/oauth/token'));
        $psrFactory->method('createResponse')->willReturn($response);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $controller = new AuthController($authServer, $psrFactory, static::createStub(RateLimiter::class), $connection);
        $request = new Request(server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->request->set('grant_type', $grantType);

        $controller->token($request);
    }

    public function testOAuthErrorCommitsReplayRevocation(): void
    {
        $response = new Response();
        $authServer = static::createStub(AuthorizationServer::class);
        $authServer->method('respondToAccessTokenRequest')
            ->willThrowException(OAuthServerException::invalidRefreshToken('Token has been revoked'));

        $psrFactory = static::createStub(PsrHttpFactory::class);
        $psrFactory->method('createRequest')->willReturn(new ServerRequest('POST', '/api/oauth/token'));
        $psrFactory->method('createResponse')->willReturn($response);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $controller = new AuthController($authServer, $psrFactory, static::createStub(RateLimiter::class), $connection);
        $request = new Request(server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->request->set('grant_type', 'refresh_token');

        $this->expectException(OAuthServerException::class);
        $controller->token($request);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function transactionalGrantTypes(): iterable
    {
        yield 'authorization code' => ['authorization_code'];
        yield 'refresh token' => ['refresh_token'];
    }
}
