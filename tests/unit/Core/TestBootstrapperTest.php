<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
