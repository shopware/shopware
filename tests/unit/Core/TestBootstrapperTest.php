<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
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

    public function testAddCallingPluginActivatesThePluginNamedInItsComposerJson(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';
        $pluginPath = $projectDir . '/custom/static-plugins/SwagStaticAnalysis';

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willThrowException(new \RuntimeException('Kernel plugin loader is not available.'));

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $classLoader = (new TestBootstrapper())
                ->setProjectDir($projectDir)
                ->addCallingPlugin($pluginPath . '/composer.json')
                ->getClassLoader();

            static::assertSame([$pluginPath . '/tests/'], $classLoader->getPrefixesPsr4()['SwagStaticAnalysis\\Tests\\']);
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testGetClassLoaderRegistersActivePluginAutoloadDevFromKernelPluginLoader(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';
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

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $classLoader = (new TestBootstrapper())
                ->setProjectDir($projectDir)
                ->addActivePlugins('SwagCmsElements')
                ->getClassLoader();

            static::assertSame([$pluginDir . '/tests/'], $classLoader->getPrefixesPsr4()['SwagCmsElements\\Tests\\']);
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testGetPluginPathFindsPluginFromKernelPluginLoader(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';
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

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            static::assertSame($projectDir . '/' . $vendorPluginPath, (new TestBootstrapper())->setProjectDir($projectDir)->getPluginPath('SwagCmsElements'));
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testGetPluginPathFallsBackToFilesystemForLocalPlugins(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';
        $pluginPath = $projectDir . '/custom/static-plugins/SwagStaticAnalysis';

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willThrowException(new \RuntimeException('Kernel plugin loader is not available.'));

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            static::assertSame($pluginPath, (new TestBootstrapper())->setProjectDir($projectDir)->getPluginPath('SwagStaticAnalysis'));
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testGetClassLoaderRegistersActivePluginAutoloadDevFromFilesystemFallback(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';
        $pluginPath = $projectDir . '/custom/static-plugins/SwagStaticAnalysis';

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willThrowException(new \RuntimeException('Kernel plugin loader is not available.'));

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $classLoader = (new TestBootstrapper())
                ->setProjectDir($projectDir)
                ->addActivePlugins('SwagStaticAnalysis')
                ->getClassLoader();

            static::assertSame([$pluginPath . '/tests/'], $classLoader->getPrefixesPsr4()['SwagStaticAnalysis\\Tests\\']);
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
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

    public function testGetClassLoaderThrowsExceptionWithKernelCauseWhenBothLookupsFail(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';
        $kernelException = new \RuntimeException('Kernel lookup failed');

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willThrowException($kernelException);

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $bootstrapper = new TestBootstrapper();
            $bootstrapper->setProjectDir($projectDir);
            $bootstrapper->addActivePlugins('NonExistentPlugin');

            try {
                $bootstrapper->getClassLoader();
                static::fail('Expected \RuntimeException was not thrown');
            } catch (\RuntimeException $e) {
                static::assertSame('Could not find plugin: NonExistentPlugin. The kernel plugin lookup failed before: Kernel lookup failed', $e->getMessage());
                static::assertSame($kernelException, $e->getPrevious());
            }
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testGetClassLoaderThrowsPlainExceptionWhenKernelKnowsNoSuchPlugin(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $projectDir = __DIR__ . '/_fixtures/TestBootstrapper/project';

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn(new StaticKernelPluginLoader(new ClassLoader(), null, []));

        KernelLifecycleAccessor::setKernel($kernel);

        try {
            $bootstrapper = (new TestBootstrapper())
                ->setProjectDir($projectDir)
                ->addActivePlugins('NonExistentPlugin');

            try {
                $bootstrapper->getClassLoader();
                static::fail('Expected \RuntimeException was not thrown');
            } catch (\RuntimeException $e) {
                static::assertSame('Could not find plugin: NonExistentPlugin', $e->getMessage());
                static::assertNull($e->getPrevious());
            }
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testBootstrapThrowsUnexpectedErrorInsteadOfReinstallingTheDatabase(): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();
        $cause = new \RuntimeException('Environment variable not found: "SOME_UNRELATED_VARIABLE".');

        $connection = static::createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException($cause);

        KernelLifecycleAccessor::setKernel($this->createKernelForInstallCheck($connection));

        try {
            try {
                $this->createBootstrapperForInstallCheck()->bootstrap();
                static::fail('Expected \RuntimeException was not thrown');
            } catch (\RuntimeException $e) {
                static::assertSame('Could not check whether the test database is installed: Environment variable not found: "SOME_UNRELATED_VARIABLE".', $e->getMessage());
                static::assertSame($cause, $e->getPrevious());
            }
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    public function testBootstrapInstallsWhenThePluginTableIsMissing(): void
    {
        $this->assertBootstrapStartsInstallation(new TableNotFoundException(static::createStub(DriverException::class), null));
    }

    public function testBootstrapInstallsWhenTheDatabaseIsMissing(): void
    {
        $this->assertBootstrapStartsInstallation(new ConnectionException(static::createStub(DriverException::class), null));
    }

    private function assertBootstrapStartsInstallation(\Throwable $missingDatabase): void
    {
        $previousKernel = KernelLifecycleAccessor::currentKernel();

        $connection = static::createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException($missingDatabase);

        KernelLifecycleAccessor::setKernel($this->createKernelForInstallCheck($connection));

        try {
            try {
                $this->createBootstrapperForInstallCheck()->bootstrap();
                static::fail('Expected the installation to start');
            } catch (\LogicException $e) {
                static::assertSame('Installation started', $e->getMessage());
            }
        } finally {
            KernelLifecycleAccessor::setKernel($previousKernel);
        }
    }

    /**
     * The container returns the given connection for the installation check;
     * any other service request means system:install started.
     */
    private function createKernelForInstallCheck(Connection $connection): Kernel
    {
        $container = static::createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $id) use ($connection): Connection {
            if ($id === Connection::class) {
                return $connection;
            }

            throw new \LogicException('Installation started');
        });

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getContainer')->willReturn($container);

        return $kernel;
    }

    private function createBootstrapperForInstallCheck(): TestBootstrapper
    {
        return (new TestBootstrapper())
            ->setClassLoader(static::createStub(ClassLoader::class))
            ->setDatabaseUrl('mysql://irrelevant')
            ->setLoadEnvFile(false)
            ->setForceInstall(false);
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
