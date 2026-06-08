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
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
class ServiceDefinitionTest extends TestCase
{
    use KernelTestBehaviour;

    private const BUNDLES = [
        'Administration' => 'Shopware\\Administration',
        'Core' => 'Shopware\\Core',
        'Elasticsearch' => 'Shopware\\Elasticsearch',
        'Storefront' => 'Shopware\\Storefront',
    ];

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

    public function testServiceDefinitions(): void
    {
        $basePath = __DIR__ . '/../../../../src';

        $finder = (new Finder())
            ->in($basePath)
            ->files()
            ->path('~(?:DependencyInjection/[^/]+|Resources/config/services(?:_[^/]*)?)\.xml$~');
        static::assertTrue($finder->hasResults(), 'No service definition files found. Check the base path.');

        $errors = [];
        foreach ($finder->getIterator() as $file) {
            $realPath = $file->getRealPath();
            static::assertIsString($realPath);

            $fileErrors = [];

            foreach ($this->getServiceDefinitions($realPath) as $service) {
                $fileErrors = array_merge(
                    $fileErrors,
                    $this->checkServiceParameterOrder($service),
                    $this->checkArgumentOrder($service),
                    $this->checkServiceBundleRegistration($service, $file->getRelativePathname()),
                );
            }

            $errors[$file->getRelativePathname()] = $fileErrors;
        }

        $errors = array_filter($errors);
        $errorMessage = 'Found some issues in the following files:' . \PHP_EOL . \PHP_EOL . print_r($errors, true);

        static::assertCount(0, $errors, $errorMessage);
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

    /**
     * @return list<string>
     */
    private function checkArgumentOrder(\DOMElement $service): array
    {
        $errors = [];
        foreach ($this->getArgumentDefinitions($service) as $argument) {
            if (!$argument->hasAttribute('id')) {
                continue;
            }

            $firstAttribute = $argument->attributes->item(0);
            if ($firstAttribute?->nodeName === 'type') {
                continue;
            }

            $errors[] = \sprintf(
                '%s:%d - invalid order (type should be first)',
                $argument->getAttribute('id'),
                $argument->getLineNo()
            );
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkServiceParameterOrder(\DOMElement $service): array
    {
        if (!$service->hasAttribute('id') || !$service->hasAttribute('class')) {
            return [];
        }

        if ($service->getAttribute('id') !== $service->getAttribute('class')) {
            return [];
        }

        return [
            \sprintf(
                '%s:%d - parameter class and id are identical. class parameter should be removed',
                $service->getAttribute('class'),
                $service->getLineNo()
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function checkServiceBundleRegistration(\DOMElement $service, string $relativePathname): array
    {
        if ($service->hasAttribute('alias')) {
            return [];
        }

        $serviceId = $service->getAttribute('id');
        $serviceClass = $service->getAttribute('class') ?: $serviceId;
        $currentBundle = $this->getBundleForFile($relativePathname);
        $expectedBundle = $this->getBundleForClass($serviceClass);

        if ($serviceId === '' || $currentBundle === null || $expectedBundle === null || $expectedBundle === $currentBundle) {
            return [];
        }

        return [
            \sprintf(
                'Service "%s" is registered in %s but its effective class "%s" belongs to %s:%d - register it in a %s DependencyInjection file instead',
                $serviceId,
                $currentBundle,
                $serviceClass,
                $expectedBundle,
                $service->getLineNo(),
                $expectedBundle
            ),
        ];
    }

    private function getBundleForFile(string $relativePathname): ?string
    {
        foreach (array_keys(self::BUNDLES) as $bundle) {
            if (str_starts_with($relativePathname, $bundle . '/')) {
                return $bundle;
            }
        }

        return null;
    }

    private function getBundleForClass(string $class): ?string
    {
        foreach (self::BUNDLES as $bundle => $namespace) {
            if ($class === $namespace || str_starts_with($class, $namespace . '\\')) {
                return $bundle;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function getServiceDefinitions(string $file): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            if (!$document->load($file, \LIBXML_NONET)) {
                return [];
            }

            return $this->getXmlElements($document, '//*[local-name() = "service"]');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function getArgumentDefinitions(\DOMElement $service): array
    {
        $document = $service->ownerDocument;
        static::assertInstanceOf(\DOMDocument::class, $document);

        $xpath = new \DOMXPath($document);

        return $this->getXmlElements($service, './/*[local-name() = "argument"]', $xpath);
    }

    /**
     * @return list<\DOMElement>
     */
    private function getXmlElements(\DOMNode $context, string $expression, ?\DOMXPath $xpath = null): array
    {
        if ($xpath === null) {
            $document = $context instanceof \DOMDocument ? $context : $context->ownerDocument;
            static::assertInstanceOf(\DOMDocument::class, $document);

            $xpath = new \DOMXPath($document);
        }

        $nodes = $xpath->query($expression, $context);

        if ($nodes === false) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
