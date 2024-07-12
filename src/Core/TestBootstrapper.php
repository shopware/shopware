<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

#[Package('core')]
class TestBootstrapper
{
    private ?ClassLoader $classLoader = null;

    private ?string $projectDir = null;

    private ?string $databaseUrl = null;

    private ?bool $forceInstall = null;

    private bool $forceInstallPlugins = false;

    private bool $platformEmbedded = true;

    private bool $loadEnvFile = true;

    private bool $commercialEnabled = false;

    private ?OutputInterface $output = null;

    /**
     * @var array<string>
     */
    private array $activePlugins = [];

    public function bootstrap(): TestBootstrapper
    {
        $_SERVER['TESTS_RUNNING'] = true;
        $_SERVER['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] = $this->getProjectDir();
        if (!\defined('TEST_PROJECT_DIR')) {
            \define('TEST_PROJECT_DIR', $_SERVER['PROJECT_ROOT']);
        }

        if ($this->commercialEnabled && $this->getPluginPath('SwagCommercial')) {
            $this->addActivePlugins('SwagCommercial');
        }

        $classLoader = $this->getClassLoader();

        if ($this->loadEnvFile) {
            $this->loadEnvFile();
        }

        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->getDatabaseUrl();

        KernelLifecycleManager::prepare($classLoader);

        if ($this->isForceInstall() || !$this->dbExists()) {
            $this->install();

            if (!empty($this->activePlugins)) {
                $this->installPlugins();
            }
        } elseif ($this->forceInstallPlugins) {
            $this->installPlugins();
        }

        return $this;
    }

    public function getStaticAnalyzeKernel(): StaticAnalyzeKernel
    {
        $pluginLoader = new DbalKernelPluginLoader($this->getClassLoader(), null, $this->getContainer()->get(Connection::class));

        KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

        /** @var StaticAnalyzeKernel $kernel */
        $kernel = KernelFactory::create(
            environment: 'phpstan_dev',
            debug: true,
            classLoader: $this->getClassLoader(),
            pluginLoader: $pluginLoader
        );

        $kernel->boot();

        return $kernel;
    }

    public function getClassLoader(): ClassLoader
    {
        if ($this->classLoader !== null) {
            return $this->classLoader;
        }

        $classLoader = require $this->getProjectDir() . '/vendor/autoload.php';

        $this->addPluginAutoloadDev($classLoader);

        $this->classLoader = $classLoader;

        return $classLoader;
    }

    public function getProjectDir(): string
    {
        if ($this->projectDir !== null) {
            return $this->projectDir;
        }

        if (isset($_SERVER['PROJECT_ROOT']) && \is_dir($_SERVER['PROJECT_ROOT'])) {
            return $this->projectDir = $_SERVER['PROJECT_ROOT'];
        }

        if (isset($_ENV['PROJECT_ROOT']) && \is_dir($_ENV['PROJECT_ROOT'])) {
            return $this->projectDir = $_ENV['PROJECT_ROOT'];
        }

        // only test cwd if it's not platform embedded (custom/plugins)
        if (!$this->platformEmbedded && \is_dir('vendor')) {
            return $this->projectDir = (string) getcwd();
        }

        $dir = $rootDir = __DIR__;
        while (!\is_dir($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return $rootDir;
            }
            $dir = \dirname($dir);
        }

        return $this->projectDir = $dir;
    }

    public function getDatabaseUrl(): string
    {
        if ($this->databaseUrl !== null) {
            return $this->databaseUrl;
        }

        $dbUrlParts = parse_url($_SERVER['DATABASE_URL'] ?? '') ?: [];

        $testToken = getenv('TEST_TOKEN');
        $dbUrlParts['path'] ??= 'root';

        // allows using the same database during development, by setting TEST_TOKEN=none
        if ($testToken !== 'none' && !str_ends_with($dbUrlParts['path'], 'test')) {
            $dbUrlParts['path'] .= '_' . ($testToken ?: 'test');
        }

        $auth = isset($dbUrlParts['user']) ? ($dbUrlParts['user'] . (isset($dbUrlParts['pass']) ? (':' . $dbUrlParts['pass']) : '') . '@') : '';

        return $this->databaseUrl = sprintf(
            '%s://%s%s%s%s%s',
            $dbUrlParts['scheme'] ?? 'mysql',
            $auth,
            $dbUrlParts['host'] ?? 'localhost',
            isset($dbUrlParts['port']) ? (':' . $dbUrlParts['port']) : '',
            $dbUrlParts['path'],
            isset($dbUrlParts['query']) ? ('?' . $dbUrlParts['query']) : ''
        );
    }

    public function setProjectDir(?string $projectDir): TestBootstrapper
    {
        $this->projectDir = $projectDir;

        return $this;
    }

    public function setClassLoader(ClassLoader $classLoader): TestBootstrapper
    {
        $this->classLoader = $classLoader;

        return $this;
    }

    public function setForceInstall(bool $forceInstall): TestBootstrapper
    {
        $this->forceInstall = $forceInstall;

        return $this;
    }

    public function addActivePlugins(string ...$activePlugins): TestBootstrapper
    {
        $this->activePlugins = array_unique(array_merge($this->activePlugins, $activePlugins));

        return $this;
    }

    /**
     * @param string|null $pathToComposerJson The composer.json to determine the plugin name. In most cases it's possible to find it automatically.
     *
     * Adds the calling plugin to the plugin list that is installed and activated
     */
    public function addCallingPlugin(?string $pathToComposerJson = null): TestBootstrapper
    {
        if (!$pathToComposerJson) {
            $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
            $callerFile = $trace[0]['file'] ?? '';

            $dir = \dirname($callerFile);
            $max = 10;
            while ($max-- > 0 && !\is_file($dir . '/composer.json')) {
                $dir = \dirname($dir);
            }

            if ($max <= 0) {
                throw new \RuntimeException('Failed to find plugin composer.json. Starting point ' . $callerFile);
            }

            $pathToComposerJson = $dir . '/composer.json';
        }

        if (!\is_file($pathToComposerJson)) {
            throw new \RuntimeException('Could not auto detect plugin name via composer.json. Path: ' . $pathToComposerJson);
        }

        $composer = json_decode((string) file_get_contents($pathToComposerJson), true, 512, \JSON_THROW_ON_ERROR);
        $baseClass = $composer['extra']['shopware-plugin-class'] ?? '';
        if ($baseClass === '') {
            throw new \RuntimeException('composer.json does not contain `extra.shopware-plugin-class`. Path: ' . $pathToComposerJson);
        }

        $parts = explode('\\', (string) $baseClass);
        $pluginName = end($parts);

        $this->addActivePlugins($pluginName);

        return $this;
    }

    public function setPlatformEmbedded(bool $platformEmbedded): TestBootstrapper
    {
        $this->platformEmbedded = $platformEmbedded;

        return $this;
    }

    public function setLoadEnvFile(bool $loadEnvFile): TestBootstrapper
    {
        $this->loadEnvFile = $loadEnvFile;

        return $this;
    }

    public function setDatabaseUrl(?string $databaseUrl): TestBootstrapper
    {
        $this->databaseUrl = $databaseUrl;

        return $this;
    }

    /**
     * This will NOT fail, if the plugin is not available
     */
    public function setEnableCommercial(bool $enableCommercial = true): TestBootstrapper
    {
        $this->commercialEnabled = $enableCommercial;

        return $this;
    }

    public function getOutput(): OutputInterface
    {
        if ($this->output !== null) {
            return $this->output;
        }

        return $this->output = new ConsoleOutput();
    }

    public function setOutput(?OutputInterface $output): TestBootstrapper
    {
        $this->output = $output;

        return $this;
    }

    public function setForceInstallPlugins(bool $forceInstallPlugins): TestBootstrapper
    {
        $this->forceInstallPlugins = $forceInstallPlugins;

        return $this;
    }

    public function isForceInstall(): bool
    {
        if ($this->forceInstall !== null) {
            return $this->forceInstall;
        }

        return $this->forceInstall = (bool) ($_SERVER['FORCE_INSTALL'] ?? false);
    }

    private function addPluginAutoloadDev(ClassLoader $classLoader): void
    {
        foreach ($this->activePlugins as $pluginName) {
            $pluginPath = $this->getPluginPath($pluginName);
            if (!$pluginPath) {
                throw new \RuntimeException(sprintf('Could not find plugin: %s', $pluginName));
            }
            $plugin = json_decode((string) file_get_contents($pluginPath . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

            $psr4 = $plugin['autoload-dev']['psr-4'] ?? [];
            $psr0 = $plugin['autoload-dev']['psr-0'] ?? [];

            foreach ($psr4 as $namespace => $paths) {
                if (\is_string($paths)) {
                    $paths = [$paths];
                }
                $mappedPaths = $this->mapPsrPaths($paths, $pluginPath);

                $classLoader->addPsr4($namespace, $mappedPaths);
                if ($classLoader->isClassMapAuthoritative()) {
                    $classLoader->setClassMapAuthoritative(false);
                }
            }

            foreach ($psr0 as $namespace => $paths) {
                if (\is_string($paths)) {
                    $paths = [$paths];
                }
                $mappedPaths = $this->mapPsrPaths($paths, $pluginPath);

                $classLoader->add($namespace, $mappedPaths);
                if ($classLoader->isClassMapAuthoritative()) {
                    $classLoader->setClassMapAuthoritative(false);
                }
            }
        }
    }

    public function getPluginPath(string $pluginName): ?string
    {
        $allPluginDirectories = \glob($this->getProjectDir() . '/custom/*plugins/*', \GLOB_ONLYDIR) ?: [];

        foreach ($allPluginDirectories as $pluginDir) {
            if (!is_file($pluginDir . '/composer.json')) {
                continue;
            }

            if (!is_file($pluginDir . '/src/' . $pluginName . '.php')) {
                continue;
            }

            return $pluginDir;
        }

        return null;
    }

    /**
     * @param list<string> $psr
     *
     * @return list<string>
     */
    private function mapPsrPaths(array $psr, string $pluginRootPath): array
    {
        $mappedPaths = [];
        foreach ($psr as $path) {
            $mappedPaths[] = $pluginRootPath . '/' . $path;
        }

        return $mappedPaths;
    }

    private function getKernel(): KernelInterface
    {
        return KernelLifecycleManager::getKernel();
    }

    private function getContainer(): ContainerInterface
    {
        return $this->getKernel()->getContainer();
    }

    private function dbExists(): bool
    {
        try {
            $connection = $this->getContainer()->get(Connection::class);
            $connection->executeQuery('SELECT 1 FROM `plugin`')->fetchAllAssociative();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function loadEnvFile(): void
    {
        if (!class_exists(Dotenv::class)) {
            throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
        }

        $envFilePath = $this->getProjectDir() . '/.env';
        if (\is_file($envFilePath) || \is_file($envFilePath . '.dist') || \is_file($envFilePath . '.local.php')) {
            (new Dotenv())->usePutenv()->bootEnv($envFilePath);
        }
    }

    private function install(): void
    {
        $application = new Application($this->getKernel());

        $returnCode = $application->doRun(
            new ArrayInput(
                [
                    'command' => 'system:install',
                    '--create-database' => true,
                    '--force' => true,
                    '--drop-database' => true,
                    '--basic-setup' => true,
                    '--no-assign-theme' => true,
                ]
            ),
            $this->getOutput()
        );
        if ($returnCode !== Command::SUCCESS) {
            throw new \RuntimeException('system:install failed');
        }

        // create new kernel after install
        KernelLifecycleManager::bootKernel(false);
    }

    private function installPlugins(): void
    {
        $application = new Application($this->getKernel());
        $application->doRun(new ArrayInput(['command' => 'plugin:refresh']), $this->getOutput());

        $kernel = KernelLifecycleManager::bootKernel();

        $application = new Application($kernel);

        foreach ($this->activePlugins as $activePlugin) {
            $args = [
                'command' => 'plugin:install',
                '--activate' => true,
                '--reinstall' => true,
                'plugins' => [$activePlugin],
            ];

            $returnCode = $application->doRun(new ArrayInput($args), $this->getOutput());

            if ($returnCode !== Command::SUCCESS) {
                throw new \RuntimeException('system:install failed');
            }
        }

        KernelLifecycleManager::bootKernel();
    }
}
