<?php declare(strict_types=1);

namespace Shopware\Tests\Devops\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class ServiceDefinitionTest extends TestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        $classLoader = require __DIR__ . '/../../../../vendor/autoload.php';

        $kernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = ServiceDefinitionTestKernel::class;
        try {
            $kernel = KernelFactory::create(
                environment: 'prod',
                debug: true,
                classLoader: $classLoader,
                pluginLoader: new StaticKernelPluginLoader($classLoader)
            );
        } finally {
            KernelFactory::$kernelClass = $kernelClass;
        }
        static::assertInstanceOf(TestKernel::class, $kernel);
        $this->kernel = $kernel;
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }
    }

    public function testEverythingIsInstantiatable(): void
    {
        $excludes = [
            '_dummy_es_env_usage',
            'kernel.bundles',
            'shopware.cache.invalidator.storage.redis', // causes redis connect
            'shopware.cache.invalidator.storage.redis_adapter',  // causes redis connect
        ];

        $container = $this->kernel->getContainer();
        static::assertInstanceOf(Container::class, $container);

        $services = array_filter($container->getServiceIds(), static fn (string $serviceId) => !\in_array($serviceId, $excludes, true));
        $errors = [];
        foreach ($services as $serviceId) {
            try {
                $container->get($serviceId);
            } catch (\Throwable $t) {
                $errors[] = $serviceId . ':' . $t->getMessage();
            }
        }

        static::assertCount(0, $errors, 'Found invalid services: ' . print_r($errors, true));
    }

    public function testContainerLintCommand(): void
    {
        $command = $this->kernel->getContainer()->get('console.command.container_lint');
        $command->setApplication(new Application($this->kernel));
        $commandTester = new CommandTester($command);

        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            $commandTester->execute([]);
        } finally {
            restore_error_handler();
        }

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console lint:container\" returned errors:\n" . $commandTester->getDisplay()
        );
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ServiceDefinitionTestKernel extends TestKernel implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || str_starts_with($id, '.')) {
                continue;
            }

            if (str_starts_with($definition->getClass() ?? '', 'Shopware\\') || str_starts_with($id, 'shopware.') || $id === 'console.command.container_lint') {
                $definition->setPublic(true);
            }
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        // Keep Shopware services accessible without retaining unused vendor definitions.
        $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_REMOVING);
    }
}
