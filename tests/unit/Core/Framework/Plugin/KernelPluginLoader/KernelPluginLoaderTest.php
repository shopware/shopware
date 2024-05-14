<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;

/**
 * @internal
 */
#[CoversClass(KernelPluginLoader::class)]
class KernelPluginLoaderTest extends TestCase
{
    #[DataProvider('classLoaderDataProvider')]
    public function testClassMapAuthoritativeWillBeDeactivated(bool $enabled): void
    {
        $classLoader = new ClassLoader();
        $classLoader->setClassMapAuthoritative($enabled);

        $fakeLoader = new StaticKernelPluginLoader(
            $classLoader,
            null,
            [
                [
                    'name' => 'ExampleBundle',
                    'baseClass' => ExampleBundle::class,
                    'path' => __DIR__ . '/../_fixtures/ExampleBundle',
                    'active' => true,
                    'managedByComposer' => false,
                    'autoload' => [
                        'psr-4' => [
                            'ExampleBundle\\' => '',
                        ],
                    ],
                ],
            ]
        );

        $fakeLoader->initializePlugins(__DIR__);

        static::assertFalse($classLoader->isClassMapAuthoritative());
    }

    #[DataProvider('classLoaderDataProvider')]
    public function testWithComposerManaged(bool $enabled): void
    {
        $classLoader = new ClassLoader();
        $classLoader->setClassMapAuthoritative($enabled);

        $fakeLoader = new StaticKernelPluginLoader(
            $classLoader,
            null,
            [
                [
                    'name' => 'ExampleBundle',
                    'baseClass' => ExampleBundle::class,
                    'path' => __DIR__ . '/../_fixtures/ExampleBundle',
                    'active' => true,
                    'managedByComposer' => true,
                    'autoload' => [
                        'psr-4' => [
                            'ExampleBundle\\' => '',
                        ],
                    ],
                ],
            ]
        );

        $fakeLoader->initializePlugins(__DIR__);

        static::assertSame($enabled, $classLoader->isClassMapAuthoritative());
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function classLoaderDataProvider(): iterable
    {
        yield 'classMapAuthoritative' => [true];
        yield 'notClassMapAuthoritative' => [false];
    }
}
