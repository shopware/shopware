<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Controller\InstallerController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $container = InstallerControllerFixture::getInstallerContainer($twig);

        $requestStack = $container->get('request_stack');
        $containerRequest = $requestStack->getCurrentRequest();
        static::assertInstanceOf(Request::class, $containerRequest);

        $containerRequest->getSession()->set('extendSteps', true);
        $controller->setContainer($container);

        $response = $controller->welcome();
        static::assertSame('welcome page', $response->getContent());
    }
}
