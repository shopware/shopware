<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
#[Group('slow')]
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
        /** @var TestKernel $separateKernel */
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
    }

    public function testServiceDefinitionNaming(): void
    {
        $basePath = __DIR__ . '/../../../';

        $xmlFiles = (new Finder())->in($basePath)->files()->path('~DependencyInjection/[^/]+\.xml$~')->getIterator();

        $errors = [];
        foreach ($xmlFiles as $file) {
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
        $command = $this->getContainer()->get('console.command.container_lint');
        $command->setApplication(new Application(KernelLifecycleManager::getKernel()));
        $commandTester = new CommandTester($command);

        set_error_handler(fn (): bool => true, \E_USER_DEPRECATED);
        $commandTester->execute([]);
        restore_error_handler();

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
            /** @var positive-int $position */
            $position = $fullMatch[1];
            $errors[] = \sprintf(
                '%s:%d - invalid order (type should be first)',
                (string) ($match['id'][0] ?? $fullMatch[0]),
                $this->getLineNumber($content, $position)
            );
        }

        return $errors;
    }

    /**
     * @return array<string>
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
            /** @var positive-int $position */
            $position = $fullMatch[1];
            $errors[] = \sprintf(
                '%s:%d - parameter class and id are identical. class parameter should be removed',
                (string) ($match['class'][0] ?? $fullMatch[0]),
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
