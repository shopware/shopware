<?php declare(strict_types=1);

namespace Shopware\Core;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Api\Controller\FallbackController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Util\VersionParser;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Route;

#[Package('core')]
class Kernel extends HttpKernel
{
    use MicroKernelTrait;

    final public const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    /**
     * @var string Fallback version if nothing is provided via kernel constructor
     */
    final public const SHOPWARE_FALLBACK_VERSION = '6.5.9999999.9999999-dev';

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

    /**
     * {@inheritdoc}
     */
    public function __construct(
        string $environment,
        bool $debug,
        KernelPluginLoader $pluginLoader,
        private string $cacheId,
        ?string $version = self::SHOPWARE_FALLBACK_VERSION,
        ?Connection $connection = null,
        ?string $projectDir = null
    ) {
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
        self::$connection = $connection;

        $this->pluginLoader = $pluginLoader;

        $version = VersionParser::parseShopwareVersion($version);
        $this->shopwareVersion = $version['version'];
        $this->shopwareVersionRevision = $version['revision'];
        $this->projectDir = $projectDir;
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        /** @var array<class-string<Bundle>, array<string, bool>> $bundles */
        $bundles = require $this->getProjectDir() . '/config/bundles.php';
        $instanciatedBundleNames = [];

        foreach ($bundles as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                $bundle = new $class();
                $instanciatedBundleNames[] = $bundle->getName();

                yield $bundle;
            }
        }

        yield from $this->pluginLoader->getBundles($this->getKernelParameters(), $instanciatedBundleNames);
    }

    public function getProjectDir(): string
    {
        if ($this->projectDir === null) {
            if ($dir = $_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? false) {
                return $this->projectDir = $dir;
            }

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
        if (self::$connection) {
            return self::$connection;
        }

        self::$connection = MySQLFactory::create();

        return self::$connection;
    }

    public function getCacheDir(): string
    {
        return sprintf(
            '%s/var/cache/%s_h%s%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
            $this->getCacheHash(),
            EnvironmentHelper::getVariable('TEST_TOKEN') ?? ''
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

    public function reboot(?string $warmupDir, ?KernelPluginLoader $pluginLoader = null, ?string $cacheId = null): void
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
        $container->setParameter('.container.dumper.inline_class_loader', true);
        $container->setParameter('.container.dumper.inline_factories', true);

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
     *
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $activePluginMeta = [];

        foreach ($this->pluginLoader->getPluginInstances()->getActives() as $plugin) {
            $class = $plugin::class;
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

        $pluginHash = md5((string) json_encode($plugins, \JSON_THROW_ON_ERROR));

        return md5((string) \json_encode([
            $this->cacheId,
            substr((string) $this->shopwareVersionRevision, 0, 8),
            substr($pluginHash, 0, 8),
            EnvironmentHelper::getVariable('DATABASE_URL', ''),
        ], \JSON_THROW_ON_ERROR));
    }

    protected function initializeDatabaseConnectionVariables(): void
    {
        $shopwareSkipConnectionVariables = EnvironmentHelper::getVariable('SHOPWARE_SKIP_CONNECTION_VARIABLES', false);

        if ($shopwareSkipConnectionVariables) {
            return;
        }

        $connection = self::getConnection();

        try {
            $setSessionVariables = (bool) EnvironmentHelper::getVariable('SQL_SET_DEFAULT_SESSION_VARIABLES', true);
            $connectionVariables = [];

            $timeZoneSupportEnabled = (bool) EnvironmentHelper::getVariable('SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED', false);
            if ($timeZoneSupportEnabled) {
                $connectionVariables[] = 'SET @@session.time_zone = "UTC"';
            }

            if ($setSessionVariables) {
                $connectionVariables[] = 'SET @@group_concat_max_len = CAST(IF(@@group_concat_max_len > 320000, @@group_concat_max_len, 320000) AS UNSIGNED)';
                $connectionVariables[] = 'SET sql_mode=(SELECT REPLACE(@@sql_mode,\'ONLY_FULL_GROUP_BY\',\'\'))';
            }

            if (empty($connectionVariables)) {
                return;
            }
            $connection->executeQuery(implode(';', $connectionVariables));
        } catch (\Throwable) {
        }
    }

    /**
     * Dumps the preload file to an always known location outside the generated cache folder name
     */
    protected function dumpContainer(ConfigCache $cache, ContainerBuilder $container, string $class, string $baseClass): void
    {
        parent::dumpContainer($cache, $container, $class, $baseClass);
        $cacheDir = $this->getCacheDir();
        $cacheName = basename($cacheDir);
        $fileName = substr(basename($cache->getPath()), 0, -3) . 'preload.php';

        $preloadFile = \dirname($cacheDir) . '/opcache-preload.php';

        $loader = <<<PHP
<?php

require_once __DIR__ . '/#CACHE_PATH#';
PHP;

        file_put_contents($preloadFile, str_replace(
            ['#CACHE_PATH#'],
            [$cacheName . '/' . $fileName],
            $loader
        ));
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
        $route->setDefault(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);

        $routes->add('root.fallback', $route->getPath());
    }
}
