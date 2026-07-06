<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\Controller;

use League\OAuth2\Server\AuthorizationServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\Controller\SsoController;
use Shopware\Core\Framework\Sso\LoginResponseService;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Sso\StateValidator;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(SsoController::class)]
class SsoControllerTest extends TestCase
{
    use EnvTestBehaviour;

    public function testSsoAuthWithLoginPrompt(): void
    {
        $loginConfigService = $this->createMock(LoginConfigService::class);
        $loginConfigService->expects($this->once())
            ->method('createRedirectUrl')
            ->with('random-string', true)
            ->willReturn('https://example.com');

        $session = new Session(new MockArraySessionStorage());
        $session->set(StateValidator::SESSION_KEY, 'random-string');

        $request = new Request(['usePromptLogin' => '1']);
        $request->setSession($session);

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $response = $this->createController($loginConfigService, $router)->ssoAuth($request);

        static::assertSame('https://example.com', $response->getTargetUrl());
    }

    public function testSsoAuthWithoutSessionStateRedirectsToAdmin(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('administration.index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/admin');

        $loginConfigService = $this->createMock(LoginConfigService::class);
        $loginConfigService->expects($this->never())->method('createRedirectUrl');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->createController($loginConfigService, $router)->ssoAuth($request);

        static::assertSame('https://example.com/admin', $response->getTargetUrl());
    }

    public function testSsoAuthFallsBackToAppUrlWhenAdministrationRouteMissing(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->willThrowException(new RouteNotFoundException());

        $request = new Request();
        $request->headers->set('referer', 'https://attacker.example/poc');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setEnvVars(['APP_URL' => 'https://example.com']);

        $response = $this->createController($this->createMock(LoginConfigService::class), $router)->ssoAuth($request);

        static::assertSame('https://example.com/admin', $response->getTargetUrl());
    }

    private function createController(LoginConfigService $loginConfigService, RouterInterface $router): SsoController
    {
        return new SsoController(
            $this->createMock(AuthorizationServer::class),
            $this->createMock(PsrHttpFactory::class),
            $loginConfigService,
            $this->createMock(LoginResponseService::class),
            $this->createMock(StateValidator::class),
            $this->createMock(SsoUserService::class),
            $this->createMock(SsoUserInvitationMailService::class),
            $this->createMock(SsoService::class),
            $router,
        );
    }
}
