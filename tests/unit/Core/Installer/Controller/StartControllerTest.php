<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\InstallerController;
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
#[CoversClass(StartController::class)]
#[CoversClass(InstallerController::class)]
class StartControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testWelcomeRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@Installer/installer/welcome.html.twig', $this->getDefaultViewParams())
            ->willReturn('languages');

        $controller = new StartController();
        $controller->setContainer($this->getInstallerContainer($twig));

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
        $controller->setContainer($this->getInstallerContainer($twig, ['router' => $router]));

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/installer?ext_steps=1');
        $request->setSession($session);

        $response = $controller->start($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/requirements', $response->getTargetUrl());
        static::assertTrue($session->get('extendSteps'));
    }

    public function testExtendedStepsAddedToMenu(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/welcome.html.twig',
                static::callback(function (array $params): bool {
                    $expectedMenuWithExtendedSteps = [
                        [
                            'label' => 'start',
                            'active' => true,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'configure_php',
                            'active' => false,
                            'isCompleted' => true,
                        ],
                        [
                            'label' => 'download',
                            'active' => false,
                            'isCompleted' => true,
                        ],
                        [
                            'label' => 'requirements',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'license',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'database-configuration',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'database-import',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'configuration',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'translation',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                        [
                            'label' => 'finish',
                            'active' => false,
                            'isCompleted' => false,
                        ],
                    ];
                    static::assertArrayHasKey('menu', $params);
                    static::assertSame($expectedMenuWithExtendedSteps, $params['menu']);

                    return true;
                })
            )
            ->willReturn('welcome page');

        $controller = new StartController();
        $container = $this->getInstallerContainer($twig);

        $requestStack = $container->get('request_stack');
        $containerRequest = $requestStack->getCurrentRequest();
        static::assertInstanceOf(Request::class, $containerRequest);

        $containerRequest->getSession()->set('extendSteps', true);
        $controller->setContainer($container);

        $response = $controller->start($containerRequest);
        static::assertSame('welcome page', $response->getContent());
    }
}
