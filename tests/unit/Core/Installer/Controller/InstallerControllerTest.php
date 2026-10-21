<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('defaultLanguagesProvider')]
    public function testSupportedLanguagesFallBackToSortedDefaults(array $parameters): void
    {
        static::assertSame(
            ['de', 'en', 'en-US'],
            array_keys($this->renderSupportedLanguages($parameters)),
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('customLanguagesProvider')]
    public function testSupportedLanguagesArePassedThroughUnchanged(array $parameters): void
    {
        static::assertSame($parameters['supportedLanguages'], $this->renderSupportedLanguages($parameters));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function defaultLanguagesProvider(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['supportedLanguages' => null]];
        yield 'empty array' => [['supportedLanguages' => []]];
        yield 'non-empty string' => [['supportedLanguages' => 'de']];
        yield 'empty string' => [['supportedLanguages' => '']];
        yield 'zero string' => [['supportedLanguages' => '0']];
        yield 'zero int' => [['supportedLanguages' => 0]];
        yield 'false' => [['supportedLanguages' => false]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function customLanguagesProvider(): iterable
    {
        yield 'non-empty array' => [['supportedLanguages' => ['fr' => ['id' => 'fr-FR', 'label' => 'Francais']]]];
    }

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

        $response = $this->runWithErrorsAsExceptions(static fn (): Response => $controller->welcome());
        static::assertSame('welcome page', $response->getContent());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderSupportedLanguages(array $parameters): mixed
    {
        $rendered = null;
        $twig = static::createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $view, array $params) use (&$rendered): string {
            $rendered = $params['supportedLanguages'];

            return 'page';
        });

        $controller = new class extends InstallerController {
            /**
             * @param array<string, mixed> $parameters
             */
            public function welcome(array $parameters): Response
            {
                return $this->renderInstaller('@Installer/installer/welcome.html.twig', $parameters);
            }
        };
        $controller->setContainer(InstallerControllerFixture::getInstallerContainer($twig));
        $this->runWithErrorsAsExceptions(static fn (): Response => $controller->welcome($parameters));

        return $rendered;
    }

    /**
     * @param \Closure(): Response $callback
     */
    private function runWithErrorsAsExceptions(\Closure $callback): Response
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
