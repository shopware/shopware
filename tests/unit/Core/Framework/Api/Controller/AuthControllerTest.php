<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\AuthController;
use Shopware\Core\Framework\Log\Package;
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
    public function testNormalizesLimiterKey(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')->with(RateLimiter::OAUTH, 'admin-10.0.0.1');
        $rateLimiter->expects($this->once())->method('reset')->with(RateLimiter::OAUTH, 'admin-10.0.0.1');

        $response = new Response();
        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->method('respondToAccessTokenRequest')->willReturn($response);
        $factory = $this->createMock(PsrHttpFactory::class);
        $factory->method('createRequest')->willReturn(new ServerRequest('POST', '/api/oauth/token'));
        $factory->method('createResponse')->willReturn($response);
        $controller = new AuthController($authorizationServer, $factory, $rateLimiter);

        $controller->token(new Request(request: ['username' => " \u{00A0}Admin\u{3000}  "], server: ['REMOTE_ADDR' => '10.0.0.1']));
    }
}
