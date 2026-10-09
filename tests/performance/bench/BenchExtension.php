<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @internal - only for performance benchmarks
 */
class BenchExtension implements ExtensionInterface
{
    private ?string $runGroup = null;

    private ?OptionsResolver $resolver = null;

    public function load(Container $container): void
    {
        if (!$this->resolver instanceof OptionsResolver) {
            throw new \LogicException(self::class . '::configure must be called before running the load method');
        }

        $_SERVER['APP_ENV'] = 'test';

        if (isset($_SERVER['DATABASE_URL'])) {
            $url = $_SERVER['DATABASE_URL'];
        }

        $bootstrapper = (new TestBootstrapper())
            ->setOutput(new ConsoleOutput())
            ->setForceInstall(static::parseEnvVar('FORCE_INSTALL', true))
            ->setForceInstallPlugins(static::parseEnvVar('FORCE_INSTALL_PLUGINS', true))
            ->setPlatformEmbedded(static::parseEnvVar('PLATFORM_EMBEDDED'))
            ->setEnableCommercial(static::parseEnvVar('ENABLE_COMMERCIAL'))
            ->setLoadEnvFile(static::parseEnvVar('LOAD_ENV_FILE', true))
            ->setProjectDir($_ENV['PROJECT_DIR'] ?? null)
            ->bootstrap();

        $fixtures = new Fixtures();
        $fixtures->load(__DIR__ . '/data-initial.json');
        Fixtures::getIds(); // Load the saved IDs to use them for the customer
        $fixtures->load(__DIR__ . '/data-customer.json'); // Customer needs some data to be present in the DB, so it could not be created in the same sync operation as the other data

        // TODO: Resolve autoloading to [Commercial]/tests/performance/bench so native phpbench `core.extensions` can be used
        $fixturePath = $bootstrapper->getPluginPath('SwagCommercial') . '/tests/performance/bench/Common';
        $symfonyContainer = KernelLifecycleManager::getKernel()->getContainer();
        $container->register('symfony-container', static fn () => $symfonyContainer);
        $runGroup = $this->getRunGroup();

        foreach ($this->findFixtures($fixturePath) as $fixtureFile) {
            // Get classes before requiring the file
            $declaredBefore = get_declared_classes();
            require $fixtureFile;
            // Get classes after requiring the file
            $declaredAfter = get_declared_classes();
            // Find all newly declared classes (fixes bug where parent class was incorrectly picked up)
            $newClasses = array_diff($declaredAfter, $declaredBefore);

            if ($newClasses === []) {
                continue;
            }

            // Iterate through all newly declared classes to find the fixture
            // (a file may declare helper classes before the actual fixture class)
            foreach ($newClasses as $currentFixtureClass) {
                if (
                    is_subclass_of($currentFixtureClass, AbstractGroupAwareExtension::class)
                    && \defined("$currentFixtureClass::TARGET_GROUP")
                    && \constant("$currentFixtureClass::TARGET_GROUP") === $runGroup
                ) {
                    $fixture = new $currentFixtureClass($container);
                    $fixture->configure($this->resolver);
                    $fixture->load($container);
                    break; // Found and loaded the fixture, no need to check other classes from this file
                }
            }
        }

        if (isset($url)) {
            $_SERVER['DATABASE_URL'] = $url;
        }
    }

    public function configure(OptionsResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    public static function parseEnvVar(string $varName, mixed $default = false): mixed
    {
        if (isset($_SERVER[$varName])) {
            return filter_var($_SERVER[$varName], \FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }

    /**
     * @return \Generator<string>
     */
    private function findFixtures(string $fixturePath): \Generator
    {
        if (is_file($fixturePath) && preg_match('/\.php$/', basename($fixturePath))) {
            yield $fixturePath;
        } elseif (is_dir($fixturePath)) {
            $directory = scandir($fixturePath);
            if (\is_array($directory)) {
                foreach ($directory as $subName) {
                    if (!preg_match('/^\.+$/', $subName)) {
                        yield from $this->findFixtures($fixturePath . \DIRECTORY_SEPARATOR . $subName);
                    }
                }
            }
        }
    }

    private function getRunGroup(): ?string
    {
        if ($this->runGroup !== null) {
            return $this->runGroup;
        }
        foreach ($GLOBALS['argv'] as $inputArg) {
            if (\preg_match('/^--group=([\-\w]+)/', (string) $inputArg, $matches) === 1) {
                $this->runGroup = $matches[1];

                break;
            }
        }

        return $this->runGroup;
    }
}
