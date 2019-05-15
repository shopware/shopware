<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;

class KernelTest extends TestCase
{
    public function testRegisterPluginNamespaceWithoutPlugins(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->expects(static::never())->method(static::anything());

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [];

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceIgnoreComposerPlugins(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->expects(static::never())->method(static::anything());

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => true,
            ],
        ];

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceWithoutAutoload(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->expects(static::never())->method(static::anything());

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to register plugin "Test" in autoload.');

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceWithEmptyAutoload(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->expects(static::never())->method(static::anything());

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
                'autoload' => [],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to register plugin "Test" in autoload.');

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceStringPsr4(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
                'path' => 'custom/plugins/TestPlugin',
                'autoload' => json_encode([
                    'psr-4' => [
                        'Test\\' => 'src',
                    ],
                ]),
            ],
        ];

        $classLoader->expects(static::once())->method('addPsr4')->with('Test\\', [dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/src'], false);

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceArrayPsr4(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
                'path' => 'custom/plugins/TestPlugin',
                'autoload' => json_encode([
                    'psr-4' => [
                        'Test\\' => ['src', 'components'],
                    ],
                ]),
            ],
        ];

        $classLoader->expects(static::once())->method('addPsr4')->with('Test\\', [
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/src',
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/components',
        ], false);

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceStringPsr0(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
                'path' => 'custom/plugins/TestPlugin',
                'autoload' => json_encode([
                    'psr-0' => [
                        'Test_' => 'src',
                    ],
                ]),
            ],
        ];

        $classLoader->expects(static::once())->method('add')->with('Test_', [dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/src'], false);

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceArrayPsr0(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
                'path' => 'custom/plugins/TestPlugin',
                'autoload' => json_encode([
                    'psr-0' => [
                        'Test_' => ['src', 'components'],
                    ],
                ]),
            ],
        ];

        $classLoader->expects(static::once())->method('add')->with('Test_', [
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/src',
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/components',
        ], false);

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }

    public function testRegisterPluginNamespaceMixed(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $kernel = new Kernel('test', true, $classLoader);
        $registerPluginNamespaceMethod = ReflectionHelper::getMethod(Kernel::class, 'registerPluginNamespaces');
        $plugins = [
            [
                'managed_by_composer' => false,
                'base_class' => 'Test',
                'path' => 'custom/plugins/TestPlugin',
                'autoload' => json_encode([
                    'psr-0' => [
                        'Test_' => 'src',
                    ],
                    'psr-4' => [
                        'Test\\' => ['src', 'components'],
                    ],
                ]),
            ],
        ];

        $classLoader->expects(static::once())->method('addPsr4')->with('Test\\', [
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/src',
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/components',
        ], false);

        $classLoader->expects(static::once())->method('add')->with('Test_', [
            dirname(__DIR__, 2) . '/custom/plugins/TestPlugin/src',
        ], false);

        $registerPluginNamespaceMethod->invoke($kernel, $plugins);
    }
}
