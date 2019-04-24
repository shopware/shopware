<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Api\Controller\FallbackController;
use Shopware\Core\Framework\Framework;
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
     * {@inheritdoc}
     */
    public function __construct(string $environment, bool $debug, ClassLoader $classLoader)
    {
        parent::__construct($environment, $debug);

        self::$plugins = new KernelPluginCollection();
        self::$connection = null;

        $this->classLoader = $classLoader;
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

        yield from self::$plugins->getActives();
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
        return sprintf(
            '%s/var/cache/%s_%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
            Framework::REVISION
        );
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/logs';
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
                'kernel.plugin_dir' => $this->getPluginDir(),
                'kernel.active_plugins' => $activePluginMeta,
            ]
        );
    }

    protected function getContainerClass(): string
    {
        $pluginHash = sha1(implode('', array_keys(self::getPlugins()->getActives())));

        return $this->name
            . ucfirst($this->environment)
            . $pluginHash
            . ($this->debug ? 'Debug' : '')
            . 'ProjectContainer';
    }

    protected function initializePlugins(): void
    {
        $sql = <<<SQL
SELECT `name`, IF(`active` = 1 AND `installed_at` IS NOT NULL, 1, 0) AS active, `path`, `autoload`, `managed_by_composer` FROM `plugin`
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
            if ($bundle instanceof \Shopware\Core\Framework\Bundle) {
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

            if (empty($plugin['autoload'])) {
                throw new \RuntimeException(sprintf('Unable to register plugin "%s" in autoload.', $plugin['name']));
            }

            $autoload = json_decode($plugin['autoload'], true);

            $psr4 = $autoload['psr-4'] ?? [];
            $psr0 = $autoload['psr-0'] ?? [];

            if (empty($psr4) && empty($psr0)) {
                throw new \RuntimeException(sprintf('Unable to register plugin "%s" in autoload.', $plugin['name']));
            }

            foreach ($psr4 as $namespace => $path) {
                $this->classLoader->addPsr4($namespace, $this->getProjectDir() . '/' . $plugin['path'] . '/' . $path);
            }

            foreach ($psr0 as $namespace => $path) {
                $this->classLoader->add($namespace, $this->getProjectDir() . '/' . $plugin['path'] . '/' . $path);
            }
        }
    }

    private function instantiatePlugins(array $plugins): void
    {
        foreach ($plugins as $pluginData) {
            $className = $pluginData['name'];

            $pluginClassFilePath = $this->classLoader->findFile($className);
            if ($pluginClassFilePath === false) {
                continue;
            }

            if (!file_exists($pluginClassFilePath)) {
                continue;
            }

            /** @var Plugin $plugin */
            $plugin = new $className((bool) $pluginData['active']);

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
}
