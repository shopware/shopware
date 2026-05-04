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
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(SsoController::class)]
class SsoControllerTest extends TestCase
{
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

        (new SsoController(
            $this->createMock(AuthorizationServer::class),
            $this->createMock(PsrHttpFactory::class),
            $loginConfigService,
            $this->createMock(LoginResponseService::class),
            $this->createMock(StateValidator::class),
            $this->createMock(SsoUserService::class),
            $this->createMock(SsoUserInvitationMailService::class),
            $this->createMock(SsoService::class),
        ))->ssoAuth($request);
    }
}
