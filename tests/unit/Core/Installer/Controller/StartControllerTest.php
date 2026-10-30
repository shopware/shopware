<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Controller\StartController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StartController::class)]
class StartControllerTest extends TestCase
{
    public function testWelcomeRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@Installer/installer/welcome.html.twig', InstallerControllerFixture::getDefaultViewParams())
            ->willReturn('languages');

        $controller = new StartController();
        $controller->setContainer(InstallerControllerFixture::getInstallerContainer($twig));

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/installer');
        $request->setSession($session);

        $response = $controller->start($request);
        static::assertSame('languages', $response->getContent());
    }

    public function testSkipWelcomeRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('generate')
            ->with('installer.requirements', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/requirements');

        $controller = new StartController();
        $controller->setContainer(InstallerControllerFixture::getInstallerContainer($twig, ['router' => $router]));

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/installer?ext_steps=1');
        $request->setSession($session);

        $response = $controller->start($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/requirements', $response->getTargetUrl());
        static::assertTrue($session->get('extendSteps'));
    }
}
