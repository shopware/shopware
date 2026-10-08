<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use PHPUnit\Framework\Attributes\Group;
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
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
#[Group('slow')]
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

    public function testServiceDefinitionNaming(): void
    {
        $basePath = __DIR__ . '/../../../../src';

        $finder = (new Finder())->in($basePath)->files()->path('~DependencyInjection/[^/]+\.xml$~');
        static::assertTrue($finder->hasResults(), 'No service definition files found. Check the base path.');

        $errors = [];
        foreach ($finder->getIterator() as $file) {
            $content = $file->getContents();

            $parameterErrors = $this->checkServiceParameterOrder($content);
            $argumentErrors = $this->checkArgumentOrder($content);

            $errors[$file->getRelativePathname()] = array_merge($parameterErrors, $argumentErrors);
        }

        $errors = array_filter($errors);
        $errorMessage = 'Found some issues in the following files:' . \PHP_EOL . \PHP_EOL . print_r($errors, true);

        static::assertCount(0, $errors, $errorMessage);
    }

    public function testContainerLintCommand(): void
    {
        $command = $this->kernel->getContainer()->get('console.command.container_lint');
        $command->setApplication(new Application($this->kernel));
        $commandTester = new CommandTester($command);

        set_error_handler(fn (): bool => true, \E_USER_DEPRECATED);
        try {
            $commandTester->execute([]);
        } finally {
            restore_error_handler();
        }

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console lint:container\" returned errors:\n" . $commandTester->getDisplay()
        );
    }

    /**
     * @return array<string>
     */
    private function checkArgumentOrder(string $content): array
    {
        $matches = [];
        $result = preg_match_all(
            '/<argument (?!type="[^"]+").*id="(?<id>[^"]+)".*>/',
            $content,
            $matches,
            \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER
        );

        if (!$result || empty($matches)) {
            return [];
        }

        $errors = [];
        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $position = $fullMatch[1];
            static::assertTrue($position > 1);
            $errors[] = \sprintf(
                '%s:%d - invalid order (type should be first)',
                $match['id'][0],
                $this->getLineNumber($content, $position)
            );
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkServiceParameterOrder(string $content): array
    {
        $matches = [];
        $result = preg_match_all(
            '<service\s+(?=.*class="(?<class>[^"]+)")(?=.*id="\k{class}").*>',
            $content,
            $matches,
            \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER
        );

        // only continue if a Shopware service definition doesn't start with class followed by id
        if (!$result || empty($matches)) {
            return [];
        }

        $errors = [];
        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $position = $fullMatch[1];
            static::assertTrue($position > 1);
            $errors[] = \sprintf(
                '%s:%d - parameter class and id are identical. class parameter should be removed',
                $match['class'][0],
                $this->getLineNumber($content, $position)
            );
        }

        return $errors;
    }

    /**
     * @param int<1, max> $position
     */
    private function getLineNumber(string $content, int $position): int
    {
        [$before] = str_split($content, $position);

        return mb_strlen($before) - mb_strlen(str_replace(\PHP_EOL, '', $before)) + 1;
    }
}

/**
 * @internal
 *
 * Runs with production service definitions: the test environment loads services_test.xml,
 * whose replacement services can hide invalid production wiring.
 * The production environment has no test.service_container, so this compiler pass makes
 * Shopware services public before Symfony removes or inlines them, allowing direct instantiation
 * and container linting without replacing their production arguments or factories.
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
