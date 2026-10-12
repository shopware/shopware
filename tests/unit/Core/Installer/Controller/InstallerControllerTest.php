<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Controller\InstallerController;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallerController::class)]
class InstallerControllerTest extends TestCase
{
    public function testExtendedStepsAddedToMenu(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/welcome.html.twig',
                static::callback(static function (array $params): bool {
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

        $controller = new class extends InstallerController {
            public function welcome(): Response
            {
                return $this->renderInstaller('@Installer/installer/welcome.html.twig');
            }
        };
        $container = $this->getInstallerContainer($twig);

        $requestStack = $container->get('request_stack');
        $containerRequest = $requestStack->getCurrentRequest();
        static::assertInstanceOf(Request::class, $containerRequest);

        $containerRequest->getSession()->set('extendSteps', true);
        $controller->setContainer($container);

        $response = $controller->welcome();
        static::assertSame('welcome page', $response->getContent());
    }

    private function getInstallerContainer(Environment $twig): ContainerInterface
    {
        $container = new ContainerBuilder();
        $container->set('twig', $twig);
        $requestStack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request([], [], ['_route' => 'installer.start']);
        $request->setSession($session);
        $requestStack->push($request);
        $container->set('request_stack', $requestStack);
        $container->setParameter('shopware.installer.supportedLanguages', [
            'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
            'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
        ]);
        $container->setParameter('kernel.shopware_version', Kernel::SHOPWARE_FALLBACK_VERSION);

        return $container;
    }
}
