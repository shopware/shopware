<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\ResponseTypes\RedirectResponse as OAuthRedirectResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Controller\OAuthAuthorizeController;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(OAuthAuthorizeController::class)]
class OAuthAuthorizeControllerTest extends TestCase
{
    private const QUERY = [
        'response_type' => 'code',
        'client_id' => 'shopware-cli',
        'redirect_uri' => 'http://127.0.0.1:54321/callback',
        'state' => 'xyz',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ];

    private AuthorizationServer&MockObject $authorizationServer;

    private RateLimiter&MockObject $rateLimiter;

    private OAuthAuthorizeController $controller;

    protected function setUp(): void
    {
        $this->authorizationServer = $this->createMock(AuthorizationServer::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->controller = $this->createController($this->authorizationServer, $this->rateLimiter);
    }

    public function testAuthorizeRedirectsValidRequestToAdministrationConsentPage(): void
    {
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturnCallback(function (ServerRequestInterface $request): AuthorizationRequest {
                static::assertSame(self::QUERY, $request->getQueryParams());

                return new AuthorizationRequest();
            });

        $this->rateLimiter->expects($this->once())->method('ensureAccepted')->with(RateLimiter::OAUTH, '10.0.0.1');
        $this->rateLimiter->expects($this->once())->method('reset')->with(RateLimiter::OAUTH, '10.0.0.1');

        $response = $this->controller->authorize(Request::create('/api/oauth/authorize', 'GET', self::QUERY, server: ['REMOTE_ADDR' => '10.0.0.1']));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringStartsWith('http://shop.example/admin#/oauth/authorize?', $response->getTargetUrl());
        parse_str((string) parse_url(substr($response->getTargetUrl(), \strlen('http://shop.example/admin#')), \PHP_URL_QUERY), $forwardedQuery);
        static::assertEquals(self::QUERY, $forwardedQuery);
    }

    public function testAuthorizeFallsBackToAppUrlWithoutAdministrationBundle(): void
    {
        $this->authorizationServer->expects($this->never())->method('validateAuthorizationRequest');
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willThrowException(new RouteNotFoundException());
        $authorizationServer = static::createStub(AuthorizationServer::class);
        $authorizationServer->method('validateAuthorizationRequest')->willReturn(new AuthorizationRequest());
        $controller = $this->createController($authorizationServer, static::createStub(RateLimiter::class), $router);

        $previous = $_SERVER['APP_URL'] ?? null;
        $_SERVER['APP_URL'] = 'http://fallback.example';
        try {
            $response = $controller->authorize(Request::create('/api/oauth/authorize', 'GET', self::QUERY));
        } finally {
            if ($previous === null) {
                unset($_SERVER['APP_URL']);
            } else {
                $_SERVER['APP_URL'] = $previous;
            }
        }

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringStartsWith('http://fallback.example/admin#/oauth/authorize?', $response->getTargetUrl());
    }

    public function testAuthorizeIsThrottled(): void
    {
        $this->rateLimiter->expects($this->once())->method('ensureAccepted')->willThrowException(new RateLimitExceededException(time() + 30));
        $this->authorizationServer->expects($this->never())->method('validateAuthorizationRequest');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/Notification throttled/');

        $this->controller->authorize(Request::create('/api/oauth/authorize', 'GET', self::QUERY));
    }

    public function testAuthorizeUsesRedirectOfLibraryException(): void
    {
        $exception = new OAuthServerException('Missing code challenge', 3, 'invalid_request', 400, null, 'http://127.0.0.1:54321/callback?state=xyz');
        $this->authorizationServer->expects($this->once())->method('validateAuthorizationRequest')->willThrowException($exception);
        $this->rateLimiter->expects($this->once())->method('ensureAccepted');
        $this->rateLimiter->expects($this->never())->method('reset');

        $response = $this->controller->authorize(Request::create('/api/oauth/authorize', 'GET', self::QUERY));

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        static::assertStringStartsWith('http://127.0.0.1:54321/callback?', $location);
        static::assertStringContainsString('error=invalid_request', $location);
        static::assertStringContainsString('state=xyz', $location);
    }

    public function testAuthorizeRedirectsErrorsToRegisteredLoopbackRedirectUri(): void
    {
        $this->authorizationServer->expects($this->once())->method('validateAuthorizationRequest')
            ->willThrowException(OAuthServerException::invalidScope('unknown'));
        $this->rateLimiter->expects($this->once())->method('ensureAccepted');
        $this->rateLimiter->expects($this->never())->method('reset');

        $response = $this->controller->authorize(Request::create('/api/oauth/authorize', 'GET', self::QUERY));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringStartsWith('http://127.0.0.1:54321/callback?', $response->getTargetUrl());
        static::assertStringContainsString('error=invalid_scope', $response->getTargetUrl());
        static::assertStringContainsString('state=xyz', $response->getTargetUrl());
    }

    public function testAuthorizeNeverRedirectsToUnknownRedirectUri(): void
    {
        $exception = OAuthServerException::invalidClient(
            (new Psr17Factory())->createServerRequest('GET', '/api/oauth/authorize')
        );
        $this->authorizationServer->expects($this->once())->method('validateAuthorizationRequest')->willThrowException($exception);
        $this->rateLimiter->expects($this->once())->method('ensureAccepted');
        $this->rateLimiter->expects($this->never())->method('reset');

        $this->expectExceptionObject($exception);

        $this->controller->authorize(Request::create(
            '/api/oauth/authorize',
            'GET',
            [...self::QUERY, 'redirect_uri' => 'http://evil.example/callback']
        ));
    }

    public function testInfoDescribesTheValidatedAuthorizationRequest(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $authorizationRequest = new AuthorizationRequest();
        $authorizationRequest->setClient(new ApiClient('shopware-cli', true, 'Shopware CLI', false));
        $authorizationRequest->setRedirectUri('http://127.0.0.1:54321/callback');
        $authorizationRequest->setScopes([new WriteScope()]);

        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturnCallback(function (ServerRequestInterface $request) use ($authorizationRequest): AuthorizationRequest {
                static::assertSame(self::QUERY, $request->getQueryParams());

                return $authorizationRequest;
            });

        $response = $this->controller->info(Request::create('/api/oauth/authorize/info', 'GET', [...self::QUERY, 'ignored' => 'x']));

        static::assertSame([
            'client' => ['id' => 'shopware-cli', 'name' => 'Shopware CLI'],
            'redirectUri' => 'http://127.0.0.1:54321/callback',
            'scopes' => ['write'],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testApproveRequiresAdminApiSource(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $this->authorizationServer->expects($this->never())->method('validateAuthorizationRequest');

        $this->expectExceptionObject(ApiException::invalidAdminSource(SystemSource::class));

        $this->controller->approve($this->createApproveRequest(true), Context::createDefaultContext());
    }

    public function testApproveRequiresUserBoundToken(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $this->authorizationServer->expects($this->never())->method('validateAuthorizationRequest');

        $this->expectExceptionObject(ApiException::userNotLoggedIn());

        $this->controller->approve(
            $this->createApproveRequest(true),
            Context::createDefaultContext(new AdminApiSource(null, Uuid::randomHex()))
        );
    }

    public function testApproveCompletesRequestForUserAndReturnsRedirectUri(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $userId = Uuid::randomHex();
        $authorizationRequest = new AuthorizationRequest();

        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturnCallback(function (ServerRequestInterface $request) use ($authorizationRequest): AuthorizationRequest {
                static::assertSame(self::QUERY, $request->getQueryParams());

                return $authorizationRequest;
            });

        $this->authorizationServer->expects($this->once())
            ->method('completeAuthorizationRequest')
            ->willReturnCallback(function (AuthorizationRequest $request) use ($authorizationRequest, $userId) {
                static::assertSame($authorizationRequest, $request);
                static::assertSame($userId, $request->getUser()?->getIdentifier());
                static::assertTrue($request->isAuthorizationApproved());

                $response = new OAuthRedirectResponse();
                $response->setRedirectUri('http://127.0.0.1:54321/callback?code=abc&state=xyz');

                return $response->generateHttpResponse((new Psr17Factory())->createResponse());
            });

        $response = $this->controller->approve(
            $this->createApproveRequest(true),
            Context::createDefaultContext(new AdminApiSource($userId))
        );

        static::assertSame(
            ['redirectUri' => 'http://127.0.0.1:54321/callback?code=abc&state=xyz'],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testDenyReturnsAccessDeniedRedirectUri(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $this->authorizationServer->expects($this->once())->method('validateAuthorizationRequest')->willReturn(new AuthorizationRequest());
        $this->authorizationServer->expects($this->once())->method('completeAuthorizationRequest')
            ->willReturnCallback(static function (AuthorizationRequest $request): never {
                static::assertFalse($request->isAuthorizationApproved());

                throw OAuthServerException::accessDenied('The user denied the request', 'http://127.0.0.1:54321/callback?state=xyz');
            });

        $response = $this->controller->approve(
            $this->createApproveRequest(false),
            Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()))
        );

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertStringStartsWith('http://127.0.0.1:54321/callback?', $payload['redirectUri']);
        static::assertStringContainsString('error=access_denied', $payload['redirectUri']);
        static::assertStringContainsString('state=xyz', $payload['redirectUri']);
    }

    public function testApproveRethrowsErrorsWithoutRedirect(): void
    {
        $this->rateLimiter->expects($this->never())->method('ensureAccepted');
        $this->authorizationServer->expects($this->once())->method('validateAuthorizationRequest')->willReturn(new AuthorizationRequest());
        $exception = OAuthServerException::serverError('boom');
        $this->authorizationServer->expects($this->once())->method('completeAuthorizationRequest')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->controller->approve(
            $this->createApproveRequest(true),
            Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()))
        );
    }

    private function createController(AuthorizationServer $authorizationServer, RateLimiter $rateLimiter, ?RouterInterface $router = null): OAuthAuthorizeController
    {
        if ($router === null) {
            $router = static::createStub(RouterInterface::class);
            $router->method('generate')->willReturn('http://shop.example/admin');
        }

        $psr17Factory = new Psr17Factory();

        return new OAuthAuthorizeController(
            $authorizationServer,
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
            $psr17Factory,
            new PublicClientRegistry(['shopware-cli' => ['name' => 'Shopware CLI', 'redirect_uris' => ['http://127.0.0.1/callback']]]),
            $rateLimiter,
            $router,
        );
    }

    private function createApproveRequest(bool $approved): Request
    {
        $request = Request::create('/api/oauth/authorize', 'POST');
        $request->request->replace([...self::QUERY, 'approved' => $approved]);

        return $request;
    }
}
