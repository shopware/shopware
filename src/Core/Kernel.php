<?php declare(strict_types=1);

namespace Shopware\Core;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Controller\FallbackController;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Route;

class Kernel extends HttpKernel
{
    use MicroKernelTrait;

    /**
     * @internal
     *
     * @deprecated tag:v6.5.0 The connection requirements should be fixed
     */
    public const PLACEHOLDER_DATABASE_URL = 'mysql://_placeholder.test';

    public const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    /**
     * @var string Fallback version if nothing is provided via kernel constructor
     */
    public const SHOPWARE_FALLBACK_VERSION = '6.4.9999999.9999999-dev';

    /**
     * @var string Regex pattern for validating Shopware versions
     */
    private const VALID_VERSION_PATTERN = '#^\d\.\d+\.\d+\.(\d+|x)(-\w+)?#';

    /**
     * @var Connection|null
     */
    protected static $connection;

    /**
     * @var KernelPluginLoader
     */
    protected $pluginLoader;

    /**
     * @var string
     */
    protected $shopwareVersion;

    /**
     * @var string|null
     */
    protected $shopwareVersionRevision;

    /**
     * @var string|null
     */
    protected $projectDir;

    private bool $rebooting = false;

    private string $cacheId;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        string $environment,
        bool $debug,
        KernelPluginLoader $pluginLoader,
        string $cacheId,
        ?string $version = self::SHOPWARE_FALLBACK_VERSION,
        ?Connection $connection = null,
        ?string $projectDir = null
    ) {
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
        self::$connection = $connection;

        $this->pluginLoader = $pluginLoader;

        $this->parseShopwareVersion($version);
        $this->cacheId = $cacheId;
        $this->projectDir = $projectDir;
    }

    /**
     * @return \Generator<BundleInterface>
     *
     * @deprecated tag:v6.5.0 The return type will be native
     */
    public function registerBundles()/*: \Generator*/
    {
        /** @var array $bundles */
        $bundles = require $this->getProjectDir() . '/config/bundles.php';
        $instanciatedBundleNames = [];

        /** @var class-string<\Symfony\Component\HttpKernel\Bundle\Bundle> $class */
        foreach ($bundles as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                $bundle = new $class();
                $instanciatedBundleNames[] = $bundle->getName();

                yield $bundle;
            }
        }

        yield from $this->pluginLoader->getBundles($this->getKernelParameters(), $instanciatedBundleNames);
    }

    /**
     * @return string
     *
     * @deprecated tag:v6.5.0 The return type will be native
     */
    public function getProjectDir()/*: string*/
    {
        if ($this->projectDir === null) {
            $r = new \ReflectionObject($this);

            $dir = (string) $r->getFileName();
            if (!file_exists($dir)) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!file_exists($dir . '/vendor')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    public function boot(): void
    {
        if ($this->booted === true) {
            if ($this->debug) {
                $this->startTime = microtime(true);
            }

            return;
        }

        if ($this->debug) {
            $this->startTime = microtime(true);
        }

        if ($this->debug && !EnvironmentHelper::hasVariable('SHELL_VERBOSITY')) {
            putenv('SHELL_VERBOSITY=3');
            $_ENV['SHELL_VERBOSITY'] = 3;
            $_SERVER['SHELL_VERBOSITY'] = 3;
        }

        try {
            $this->pluginLoader->initializePlugins($this->getProjectDir());
        } catch (\Throwable $e) {
            if (\defined('\STDERR')) {
                fwrite(\STDERR, 'Warning: Failed to load plugins. Message: ' . $e->getMessage() . \PHP_EOL);
            }
        }

        // init bundles
        $this->initializeBundles();

        // init container
        $this->initializeContainer();

        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->initializeDatabaseConnectionVariables();

        $this->booted = true;
    }

    public static function getConnection(): Connection
    {
        if (!self::$connection) {
            $url = EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'));
            $parameters = [
                'url' => $url,
                'charset' => 'utf8mb4',
            ];

            if ($sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA')) {
                $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            }

            if ($sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT')) {
                $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $sslCert;
            }

            if ($sslCertKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY')) {
                $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $sslCertKey;
            }

            if (EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT')) {
                $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            self::$connection = DriverManager::getConnection($parameters, new Configuration());
        }

        return self::$connection;
    }

    public function getCacheDir(): string
    {
        return sprintf(
            '%s/var/cache/%s_h%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
            $this->getCacheHash()
        );
    }

    public function getPluginLoader(): KernelPluginLoader
    {
        return $this->pluginLoader;
    }

    public function shutdown(): void
    {
        if (!$this->booted) {
            return;
        }

        // keep connection when rebooting
        if (!$this->rebooting) {
            self::$connection = null;
        }

        parent::shutdown();
    }

    public function reboot($warmupDir, ?KernelPluginLoader $pluginLoader = null, ?string $cacheId = null): void
    {
        $this->rebooting = true;

        try {
            if ($pluginLoader) {
                $this->pluginLoader = $pluginLoader;
            }
            if ($cacheId) {
                $this->cacheId = $cacheId;
            }
            parent::reboot($warmupDir);
        } finally {
            $this->rebooting = false;
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('container.dumper.inline_class_loader', true);
        $container->setParameter('container.dumper.inline_factories', true);

        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $confDir = $this->getProjectDir() . '/config';

        $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS, 'glob');
        $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS, 'glob');

        $this->addBundleRoutes($routes);
        $this->addApiRoutes($routes);
        $this->addBundleOverwrites($routes);
        $this->addFallbackRoute($routes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $activePluginMeta = [];

        foreach ($this->pluginLoader->getPluginInstances()->getActives() as $plugin) {
            $class = \get_class($plugin);
            $activePluginMeta[$class] = [
                'name' => $plugin->getName(),
                'path' => $plugin->getPath(),
                'class' => $class,
            ];
        }

        $pluginDir = $this->pluginLoader->getPluginDir($this->getProjectDir());

        $coreDir = \dirname((string) (new \ReflectionClass(self::class))->getFileName());

        return array_merge(
            $parameters,
            [
                'kernel.cache.hash' => $this->getCacheHash(),
                'kernel.shopware_version' => $this->shopwareVersion,
                'kernel.shopware_version_revision' => $this->shopwareVersionRevision,
                'kernel.shopware_core_dir' => $coreDir,
                'kernel.plugin_dir' => $pluginDir,
                'kernel.app_dir' => rtrim($this->getProjectDir(), '/') . '/custom/apps',
                'kernel.active_plugins' => $activePluginMeta,
                'kernel.plugin_infos' => $this->pluginLoader->getPluginInfos(),
                'kernel.supported_api_versions' => [2, 3, 4],
                'defaults_bool_true' => true,
                'defaults_bool_false' => false,
                'default_whitespace' => ' ',
            ]
        );
    }

    protected function getCacheHash(): string
    {
        $plugins = [];
        foreach ($this->pluginLoader->getPluginInfos() as $plugin) {
            if ($plugin['active'] === false) {
                continue;
            }
            $plugins[$plugin['name']] = $plugin['version'];
        }

        $pluginHash = md5((string) json_encode($plugins));

        return md5((string) json_encode([
            $this->cacheId,
            mb_substr((string) $this->shopwareVersionRevision, 0, 8),
            mb_substr($pluginHash, 0, 8),
            EnvironmentHelper::getVariable('DATABASE_URL', ''),
        ]));
    }

    protected function initializeDatabaseConnectionVariables(): void
    {
        $connection = self::getConnection();

        try {
            $nonDestructiveMigrations = $connection->executeQuery('
            SELECT `creation_timestamp`
            FROM `migration`
            WHERE `update` IS NOT NULL AND `update_destructive` IS NULL
        ')->fetchAll(FetchMode::COLUMN);

            $activeMigrations = $this->container->getParameter('migration.active');

            $activeNonDestructiveMigrations = array_intersect($activeMigrations, $nonDestructiveMigrations);

            $setSessionVariables = (bool) EnvironmentHelper::getVariable('SQL_SET_DEFAULT_SESSION_VARIABLES', true);
            $connectionVariables = [];

            if ($setSessionVariables) {
                $connectionVariables[] = 'SET @@group_concat_max_len = CAST(IF(@@group_concat_max_len > 320000, @@group_concat_max_len, 320000) AS UNSIGNED)';
                $connectionVariables[] = "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))";
            }

            foreach ($activeNonDestructiveMigrations as $migration) {
                $connectionVariables[] = sprintf(
                    'SET %s = TRUE',
                    sprintf(MigrationStep::MIGRATION_VARIABLE_FORMAT, $migration)
                );
            }

            if (empty($connectionVariables)) {
                return;
            }
            $connection->executeQuery(implode(';', $connectionVariables));
        } catch (\Throwable $_) {
        }
    }

    private function addApiRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api');
    }

    private function addBundleRoutes(RoutingConfigurator $routes): void
    {
        foreach ($this->getBundles() as $bundle) {
            if ($bundle instanceof Framework\Bundle) {
                $bundle->configureRoutes($routes, $this->environment);
            }
        }
    }

    private function addBundleOverwrites(RoutingConfigurator $routes): void
    {
        foreach ($this->getBundles() as $bundle) {
            if ($bundle instanceof Framework\Bundle) {
                $bundle->configureRouteOverwrites($routes, $this->environment);
            }
        }
    }

    private function addFallbackRoute(RoutingConfigurator $routes): void
    {
        // detail routes
        $route = new Route('/');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', FallbackController::class . '::rootFallback');

        $routes->add('root.fallback', $route->getPath());
    }

    private function parseShopwareVersion(?string $version): void
    {
        // does not come from composer, was set manually
        if ($version === null || mb_strpos($version, '@') === false) {
            $this->shopwareVersion = self::SHOPWARE_FALLBACK_VERSION;
            $this->shopwareVersionRevision = str_repeat('0', 32);

            return;
        }

        [$version, $hash] = explode('@', $version);
        $version = ltrim($version, 'v');
        $version = str_replace('+', '-', $version);

        /*
         * checks if the version is a valid version pattern
         * Shopware\Core\Framework\Test\KernelTest::testItCreatesShopwareVersion()
         */
        if (!preg_match(self::VALID_VERSION_PATTERN, $version)) {
            $version = self::SHOPWARE_FALLBACK_VERSION;
        }

        $this->shopwareVersion = $version;
        $this->shopwareVersionRevision = $hash;
    }
}
