<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Api\Controller\FallbackController;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends HttpKernel
{
    use MicroKernelTrait;

    public const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    /**
     * @var string Fallback version if nothing is provided via kernel constructor
     */
    public const SHOPWARE_FALLBACK_VERSION = '9999999-dev';

    /**
     * @var Connection|null
     */
    protected static $connection;

    /**
     * @var KernelPluginCollection
     */
    protected static $plugins;

    /**
     * @var ClassLoader
     */
    protected $classLoader;

    /**
     * @var string
     */
    protected $shopwareVersion;

    /**
     * @var string|null
     */
    protected $shopwareVersionRevision;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $environment, bool $debug, ClassLoader $classLoader, ?string $version = self::SHOPWARE_FALLBACK_VERSION)
    {
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);

        self::$plugins = new KernelPluginCollection();
        self::$connection = null;

        $this->classLoader = $classLoader;
        $this->parseShopwareVersion($version);
    }

    public function registerBundles()
    {
        /** @var array $bundles */
        $bundles = require $this->getProjectDir() . '/config/bundles.php';

        foreach ($bundles as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }

        foreach (self::$plugins->getActives() as $plugin) {
            yield $plugin;
            yield from $plugin->getExtraBundles($this->classLoader);
        }
    }

    public function boot($withPlugins = true): void
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

        if ($this->debug && !isset($_ENV['SHELL_VERBOSITY']) && !isset($_SERVER['SHELL_VERBOSITY'])) {
            putenv('SHELL_VERBOSITY=3');
            $_ENV['SHELL_VERBOSITY'] = 3;
            $_SERVER['SHELL_VERBOSITY'] = 3;
        }

        $this->initializeFeatureFlags();

        if ($withPlugins) {
            $this->initializePlugins();
        }

        // init bundles
        $this->initializeBundles();

        // init container
        $this->initializeContainer();

        /** @var Bundle|ContainerAwareTrait $bundle */
        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->initializeDatabaseConnectionVariables();

        $this->booted = true;
    }

    public static function getPlugins(): KernelPluginCollection
    {
        return self::$plugins;
    }

    public static function getConnection(): Connection
    {
        if (!self::$connection) {
            $parameters = [
                'url' => getenv('DATABASE_URL'),
                'charset' => 'utf8mb4',
            ];

            self::$connection = DriverManager::getConnection($parameters, new Configuration());
        }

        return self::$connection;
    }

    public function getCacheDir(): string
    {
        $pluginHash = md5(implode('', array_keys(self::getPlugins()->getActives())));

        return sprintf(
            '%s/var/cache/%s_k%s_p%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
            substr($this->shopwareVersionRevision, 0, 8),
            substr($pluginHash, 0, 8)
        );
    }

    public function getPluginDir(): string
    {
        return $this->getProjectDir() . '/custom/plugins';
    }

    public function shutdown(): void
    {
        if (!$this->booted) {
            return;
        }

        self::$plugins = new KernelPluginCollection();
        self::$connection = null;

        parent::shutdown();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('container.dumper.inline_class_loader', true);

        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $confDir = $this->getProjectDir() . '/config';

        $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS, '/', 'glob');

        $this->addBundleRoutes($routes);
        $this->addApiRoutes($routes);
        $this->addFallbackRoute($routes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $activePluginMeta = [];

        foreach (self::getPlugins()->getActives() as $namespace => $plugin) {
            $pluginName = $plugin->getName();
            $activePluginMeta[$pluginName] = [
                'name' => $pluginName,
                'path' => $plugin->getPath(),
            ];
        }

        return array_merge(
            $parameters,
            [
                'kernel.shopware_version' => $this->shopwareVersion,
                'kernel.shopware_version_revision' => $this->shopwareVersionRevision,
                'kernel.plugin_dir' => $this->getPluginDir(),
                'kernel.active_plugins' => $activePluginMeta,
            ]
        );
    }

    protected function initializePlugins(): void
    {
        $sql = <<<SQL
SELECT `base_class`, IF(`active` = 1 AND `installed_at` IS NOT NULL, 1, 0) AS active, `path`, `autoload`, `managed_by_composer` FROM `plugin`
SQL;

        $plugins = self::getConnection()->executeQuery($sql)->fetchAll();

        $this->registerPluginNamespaces($plugins);
        $this->instantiatePlugins($plugins);
    }

    protected function initializeFeatureFlags(): void
    {
        $cacheFile = $this->getCacheDir() . '/features.php';
        $featureCache = new ConfigCache($cacheFile, $this->isDebug());

        if (!$featureCache->isFresh()) {
            /** @var Finder $files */
            $files = (new Finder())
                ->in(__DIR__ . '/Flag/')
                ->name('feature_*.php')
                ->files();

            $resources = [new FileResource(__DIR__ . '/Flag/')];
            $contents = ['<?php declare(strict_types=1);'];

            foreach ($files as $file) {
                $path = (string) $file;

                $resources[] = new FileResource($path);
                $contents[] = file_get_contents($path, false, null, 30);
            }

            $featureCache->write(implode(PHP_EOL, $contents), $resources);
        }

        require_once $cacheFile;
    }

    private function addApiRoutes(RouteCollectionBuilder $routes): void
    {
        $routes->import('.', null, 'api');
    }

    private function addBundleRoutes(RouteCollectionBuilder $routes): void
    {
        foreach ($this->getBundles() as $bundle) {
            if ($bundle instanceof Framework\Bundle) {
                $bundle->configureRoutes($routes, (string) $this->environment);
            }
        }
    }

    private function initializeDatabaseConnectionVariables(): void
    {
        /** @var Connection $connection */
        $connection = self::getConnection();

        $nonDestructiveMigrations = $connection->executeQuery('
            SELECT `creation_timestamp`
            FROM `migration`
            WHERE `update_destructive` IS NULL
        ')->fetchAll(FetchMode::COLUMN);

        $activeMigrations = $this->container->getParameter('migration.active');

        $activeNonDestructiveMigrations = array_intersect($activeMigrations, $nonDestructiveMigrations);

        $connectionVariables = [
            'SET @@group_concat_max_len = CAST(IF(@@group_concat_max_len > 320000, @@group_concat_max_len, 320000) AS UNSIGNED)',
            "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));",
        ];
        foreach ($activeNonDestructiveMigrations as $migration) {
            $connectionVariables[] = sprintf(
                'SET %s = TRUE',
                sprintf(MigrationStep::MIGRATION_VARIABLE_FORMAT, $migration)
            );
        }

        $connection->executeQuery(implode(';', $connectionVariables));
    }

    private function registerPluginNamespaces(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            // plugins managed by composer are already in the classMap
            if ($plugin['managed_by_composer']) {
                continue;
            }

            // If a plugin ships a composer autoload.php file, load this to register the namespaces of the plugin and
            // its dependencies
            $pluginAutoLoaderFile = $this->getProjectDir() . '/' . $plugin['path'] . '/vendor/autoload.php';
            if (file_exists($pluginAutoLoaderFile)) {
                require_once $pluginAutoLoaderFile;

                continue;
            }

            if (empty($plugin['autoload'])) {
                throw new \RuntimeException(sprintf('Unable to register plugin "%s" in autoload.', $plugin['base_class']));
            }

            $autoload = json_decode($plugin['autoload'], true);

            $psr4 = $autoload['psr-4'] ?? [];
            $psr0 = $autoload['psr-0'] ?? [];

            if (empty($psr4) && empty($psr0)) {
                throw new \RuntimeException(sprintf('Unable to register plugin "%s" in autoload.', $plugin['base_class']));
            }

            foreach ($psr4 as $namespace => $path) {
                if (is_string($path)) {
                    $path = [$path];
                }
                $path = $this->mapPsrPaths($path, $plugin['path']);
                $this->classLoader->addPsr4($namespace, $path);
            }

            foreach ($psr0 as $namespace => $path) {
                if (is_string($path)) {
                    $path = [$path];
                }
                $path = $this->mapPsrPaths($path, $plugin['path']);

                $this->classLoader->add($namespace, $path);
            }
        }

        // Re-register the Shopware class loader to enforce it to be always the first one
        $this->classLoader->unregister();
        $this->classLoader->register(true);
    }

    private function mapPsrPaths(array $psr, string $pluginPath): array
    {
        $mappedPaths = [];

        foreach ($psr as $path) {
            $mappedPaths[] = $this->getProjectDir() . '/' . $pluginPath . '/' . $path;
        }

        return $mappedPaths;
    }

    private function instantiatePlugins(array $plugins): void
    {
        foreach ($plugins as $pluginData) {
            $className = $pluginData['base_class'];

            $pluginClassFilePath = $this->classLoader->findFile($className);
            if ($pluginClassFilePath === false) {
                continue;
            }

            if (!file_exists($pluginClassFilePath)) {
                continue;
            }

            /** @var Plugin $plugin */
            $plugin = new $className((bool) $pluginData['active'], $this->getProjectDir() . '/' . $pluginData['path']);

            if (!$plugin instanceof Plugin) {
                throw new \RuntimeException(
                    sprintf('Plugin class "%s" must extend "%s"', \get_class($plugin), Plugin::class)
                );
            }

            self::$plugins->add($plugin);
        }
    }

    private function addFallbackRoute(RouteCollectionBuilder $routes): void
    {
        // detail routes
        $route = new Route('/');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', FallbackController::class . '::rootFallback');

        $routes->addRoute($route, 'root.fallback');
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

        // checks if the version is a valid version pattern
        if (!preg_match('#\d+\.\d+\.\d+(-\w+)?#', $version)) {
            $version = self::SHOPWARE_FALLBACK_VERSION;
        }

        $this->shopwareVersion = $version;
        $this->shopwareVersionRevision = $hash;
    }
}
