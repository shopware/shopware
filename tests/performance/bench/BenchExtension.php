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
            throw new \Exception(self::class . '::configure must be called before running the load method');
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

        (new Fixtures())->load(__DIR__ . '/data.json');

        // TODO: Resolve autoloading to [Commercial]/tests/performance/bench so native phpbench `core.extensions` can be used
        $fixturePath = $bootstrapper->getPluginPath('SwagCommercial') . '/tests/performance/bench/Common';
        $symfonyContainer = KernelLifecycleManager::getKernel()->getContainer();
        $container->register('symfony-container', fn () => $symfonyContainer);
        $runGroup = $this->getRunGroup();
        $originalClasses = get_declared_classes();
        foreach ($this->findFixtures($fixturePath) as $fixtureFile) {
            require $fixtureFile;
            $declared = get_declared_classes();
            /** @var string $currentFixtureClass */
            $currentFixtureClass = end($declared);
            if (!str_contains($currentFixtureClass, 'Fixture.php')) {
                $currentFixtureClass = $declared[\count($declared) - 2];
            }

            if (
                is_subclass_of($currentFixtureClass, AbstractGroupAwareExtension::class)
                && \constant("$currentFixtureClass::TARGET_GROUP") === $runGroup
            ) {
                $fixture = new $currentFixtureClass($container);
                $fixture->configure($this->resolver);
                $fixture->load($container);
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

    /**
     * @return mixed
     */
    public static function parseEnvVar(string $varName, mixed $default = false)
    {
        if (isset($_SERVER[$varName])) {
            return filter_var($_SERVER[$varName], \FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }

    private function findFixtures(string $fixturePath): \Generator
    {
        if (is_file($fixturePath) && preg_match('/\.php$/', basename($fixturePath))) {
            yield $fixturePath;
        } elseif (is_dir($fixturePath)) {
            $directory = scandir($fixturePath);
            if (\is_array($directory)) {
                foreach ($directory as $subName) {
                    if (!preg_match('/^\.+$/', $subName)) {
                        foreach ($this->findFixtures($fixturePath . \DIRECTORY_SEPARATOR . $subName) as $fixture) {
                            yield $fixture;
                        }
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
