<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(TestBootstrapper::class)]
class TestBootstrapperTest extends TestCase
{
    use EnvTestBehaviour;

    public function testGetDatabaseUrlWithoutSuffix(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'mysql://root:root@localhost:3306/test',
        ]);

        $testBootstrapper = new TestBootstrapper();
        static::assertSame('mysql://root:root@localhost:3306/test_test', $testBootstrapper->getDatabaseUrl());

        $this->resetEnvVars();
    }

    public function testGetDatabaseUrlWithSuffix(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'mysql://root:root@localhost:3306/test_test',
        ]);

        $testBootstrapper = new TestBootstrapper();
        static::assertSame('mysql://root:root@localhost:3306/test_test', $testBootstrapper->getDatabaseUrl());

        $this->resetEnvVars();
    }

    public function testGetDatabaseUrlAlreadySet(): void
    {
        $testBootstrapper = new TestBootstrapper();
        $testBootstrapper->setDatabaseUrl('test');

        static::assertSame('test', $testBootstrapper->getDatabaseUrl());
    }

    public function testAddCallingPlugin(): void
    {
        $testBootstrapper = new TestBootstrapper();
        $testBootstrapper->addCallingPlugin(__DIR__ . '/Framework/Plugin/Util/_fixture/LocallyInstalledPlugins/SwagTest/composer.json');

        $activePlugins = (new \ReflectionProperty($testBootstrapper, 'activePlugins'))->getValue($testBootstrapper);

        static::assertSame(['Test'], $activePlugins);
    }

    public function testGetClassLoaderRegistersActivePluginAutoloadDev(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $filesystem = new Filesystem();
        $projectDir = $this->createTemporaryProjectDir($filesystem);
        $pluginPath = 'vendor/store.shopware.com/swagcmselements';
        $pluginDir = $projectDir . '/' . $pluginPath;

        $pluginLoader = new StaticKernelPluginLoader(new ClassLoader(), null, [
            [
                'name' => 'SwagCmsElements',
                'baseClass' => 'SwagCmsElements\\SwagCmsElements',
                'active' => true,
                'path' => $pluginPath,
                'version' => '1.0.0',
                'autoload' => [],
                'managedByComposer' => true,
                'composerName' => 'store.shopware.com/swagcmselements',
            ],
        ]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $filesystem->mkdir([
                $projectDir . '/vendor',
                $pluginDir . '/tests',
            ]);
            $filesystem->dumpFile($projectDir . '/vendor/autoload.php', '<?php return new \Composer\Autoload\ClassLoader();');
            $composerJson = json_encode([
                'autoload-dev' => [
                    'psr-4' => [
                        'SwagCmsElements\\Tests\\' => 'tests/',
                    ],
                ],
            ], \JSON_THROW_ON_ERROR);
            static::assertIsString($composerJson);
            $filesystem->dumpFile($pluginDir . '/composer.json', $composerJson);

            $classLoader = (new TestBootstrapper())
                ->setProjectDir($projectDir)
                ->addActivePlugins('SwagCmsElements')
                ->getClassLoader();

            static::assertInstanceOf(ClassLoader::class, $classLoader);
            static::assertSame([$pluginDir . '/tests/'], $classLoader->getPrefixesPsr4()['SwagCmsElements\\Tests\\']);
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
            $filesystem->remove($projectDir);
        }
    }

    public function testGetPluginPathFindsPluginFromKernelPluginLoader(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $filesystem = new Filesystem();
        $projectDir = $this->createTemporaryProjectDir($filesystem);
        $vendorPluginPath = 'vendor/store.shopware.com/swagcmselements';

        $pluginLoader = new StaticKernelPluginLoader(new ClassLoader(), null, [
            [
                'name' => 'SwagCmsElements',
                'baseClass' => 'SwagCmsElements\\SwagCmsElements',
                'active' => true,
                'path' => $vendorPluginPath,
                'version' => '1.0.0',
                'autoload' => [],
                'managedByComposer' => true,
                'composerName' => 'store.shopware.com/swagcmselements',
            ],
        ]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            static::assertSame($projectDir . '/' . $vendorPluginPath, (new TestBootstrapper())->setProjectDir($projectDir)->getPluginPath('SwagCmsElements'));
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
            $filesystem->remove($projectDir);
        }
    }

    public function testBootstrapShutsDownKernelBeforeReturning(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();

        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $connection = static::createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($result);

        $container = static::createStub(ContainerInterface::class);
        $container->method('get')->willReturn($connection);

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->expects($this->once())->method('shutdown');

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $bootstrapper = (new TestBootstrapper())
                ->setClassLoader(static::createStub(ClassLoader::class))
                ->setDatabaseUrl('mysql://irrelevant')
                ->setLoadEnvFile(false);

            $bootstrapper->bootstrap();

            static::assertNull(KernelLifecycleAccessor::currentKernel(), 'bootstrap() must leave no residual kernel');
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    private function createTemporaryProjectDir(Filesystem $filesystem): string
    {
        $temporaryDirectory = \realpath(\sys_get_temp_dir());
        static::assertIsString($temporaryDirectory);

        $projectDir = $temporaryDirectory . '/' . uniqid('shopware-test-bootstrapper-', true);
        $filesystem->mkdir($projectDir);

        return $projectDir;
    }
}

/**
 * @internal
 */
class KernelLifecycleAccessor extends KernelLifecycleManager
{
    public static function setKernel(?Kernel $kernel): void
    {
        static::$kernel = $kernel;
    }

    public static function currentKernel(): ?Kernel
    {
        return static::$kernel;
    }
}
