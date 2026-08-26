<?php declare(strict_types=1);

namespace Shopware\Tests\Devops\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestKernel;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class ServiceDefinitionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEverythingIsInstantiatable(): void
    {
        $excludes = [
            '_dummy_es_env_usage',
            'kernel.bundles',
            'shopware.cache.invalidator.storage.redis', // causes redis connect
            'shopware.cache.invalidator.storage.redis_adapter',  // causes redis connect
        ];

        $classLoader = require __DIR__ . '/../../../../vendor/autoload.php';

        KernelFactory::$kernelClass = TestKernel::class;
        $separateKernel = KernelFactory::create(
            environment: 'test',
            debug: true,
            classLoader: $classLoader,
            pluginLoader: new StaticKernelPluginLoader($classLoader)
        );
        static::assertInstanceOf(TestKernel::class, $separateKernel);
        $separateKernel->boot();

        $testContainer = $separateKernel->getContainer()->get('test.service_container');

        static::assertInstanceOf(TestContainer::class, $testContainer);

        $services = array_filter($testContainer->getServiceIds(), static fn (string $serviceId) => !\in_array($serviceId, $excludes, true));
        $errors = [];
        foreach ($services as $serviceId) {
            try {
                $testContainer->get($serviceId);
            } catch (\Throwable $t) {
                $errors[] = $serviceId . ':' . $t->getMessage();
            }
        }

        static::assertCount(0, $errors, 'Found invalid services: ' . print_r($errors, true));
        // Cleanup and reset kernel class
        $separateKernel->shutdown();
        KernelFactory::$kernelClass = Kernel::class;
    }

    public function testContainerLintCommand(): void
    {
        $command = static::getContainer()->get('console.command.container_lint');
        $command->setApplication(new Application(KernelLifecycleManager::getKernel()));
        $commandTester = new CommandTester($command);

        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        $commandTester->execute([]);
        restore_error_handler();

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console lint:container\" returned errors:\n" . $commandTester->getDisplay()
        );
    }
}
