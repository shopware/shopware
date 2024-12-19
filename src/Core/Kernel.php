<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Api\Controller\FallbackController;
use Shopware\Core\Framework\Bundle as ShopwareBundle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\VersionParser;
use Shopware\Core\Service\Service;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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
    final public const SHOPWARE_FALLBACK_VERSION = '6.6.9999999-dev';

    protected static ?Connection $connection = null;

    /**
     * @var KernelPluginLoader
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $pluginLoader;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shopwareVersion;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shopwareVersionRevision;

    private bool $rebooting = false;

    /**
     * @internal
     *
     * {@inheritdoc}
     */
    public function __construct(
        string $environment,
        bool $debug,
        KernelPluginLoader $pluginLoader,
        private string $cacheId,
        string $version,
        Connection $connection,
        protected string $projectDir
    ) {
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
        self::$connection = $connection;

        $this->pluginLoader = $pluginLoader;

        $version = VersionParser::parseShopwareVersion($version);
        $this->shopwareVersion = $version['version'];
        $this->shopwareVersionRevision = $version['revision'];
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        /** @var array<class-string<Bundle>, array<string, bool>> $bundles */
        $bundles = require $this->getProjectDir() . '/config/bundles.php';
        $instanciatedBundleNames = [];

        $kernelParameters = $this->getKernelParameters();

        foreach ($bundles as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                /** @var ShopwareBundle|Bundle $bundle */
                $bundle = new $class();

                if ($this->isBundleRegistered($bundle, $instanciatedBundleNames)) {
                    continue;
                }

                $instanciatedBundleNames[] = $bundle->getName();

                yield $bundle;

                if (!$bundle instanceof ShopwareBundle) {
                    continue;
                }

                $classLoader = new ClassLoader();
                $parameters = new AdditionalBundleParameters($classLoader, new KernelPluginCollection(), $kernelParameters);
                foreach ($bundle->getAdditionalBundles($parameters) as $additionalBundle) {
                    if ($this->isBundleRegistered($additionalBundle, $instanciatedBundleNames)) {
                        continue;
                    }

                    $instanciatedBundleNames[] = $additionalBundle->getName();
                    yield $additionalBundle;
                }
            }
        }

        if ((!Feature::has('v6.7.0.0') || !Feature::isActive('v6.7.0.0')) && !isset($bundles[Service::class])) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', \sprintf('The %s bundle should be added to config/bundles.php', Service::class));
            yield new Service();
        }

        yield from $this->pluginLoader->getBundles($kernelParameters, $instanciatedBundleNames);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->getHttpKernel()->handle($request, $type, $catch);
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
            putenv('SHELL_VERBOSITY=1');
            $_ENV['SHELL_VERBOSITY'] = 1;
            $_SERVER['SHELL_VERBOSITY'] = 1;
        }

        try {
            // initialize plugins before booting
            $this->pluginLoader->initializePlugins($this->getProjectDir());
        } catch (DBALException $e) {
            if (\defined('\STDERR')) {
                fwrite(\STDERR, 'Warning: Failed to load plugins. Message: ' . $e->getMessage() . \PHP_EOL);
            }
        }

        $this->initializeDatabaseConnectionVariables();

        parent::boot();
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
        return \sprintf(
            '%s/var/cache/%s_h%s%s',
            EnvironmentHelper::getVariable('APP_CACHE_DIR', $this->getProjectDir()),
            $this->getEnvironment(),
            $this->getCacheHash(),
            EnvironmentHelper::getVariable('TEST_TOKEN') ?? ''
        );
    }

    public function getBuildDir(): string
    {
        if (EnvironmentHelper::hasVariable('APP_BUILD_DIR')) {
            return EnvironmentHelper::getVariable('APP_BUILD_DIR') . '/' . $this->environment;
        }

        return parent::getBuildDir();
    }

    public function getLogDir(): string
    {
        return (string) EnvironmentHelper::getVariable('APP_LOG_DIR', parent::getLogDir());
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
        $container->setParameter('.container.dumper.inline_factories', $this->environment !== 'test');

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

        return Hasher::hash([
            $this->cacheId,
            (string) $this->shopwareVersionRevision,
            $plugins,
        ]);
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

            /**
             * @deprecated tag:v6.7.0 - remove if clause and enforce timezone setting
             */
            $timeZoneSupportEnabled = (bool) EnvironmentHelper::getVariable('SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED', Feature::isActive('v6.7.0.0'));
            if ($timeZoneSupportEnabled) {
                $connectionVariables[] = 'SET @@session.time_zone = "+00:00"';
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

        file_put_contents(\dirname($cacheDir) . '/CACHEDIR.TAG', 'Signature: 8a477f597d28d172789f06886806bc55');

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
            if ($bundle instanceof ShopwareBundle) {
                $bundle->configureRoutes($routes, $this->environment);
            }
        }
    }

    private function addBundleOverwrites(RoutingConfigurator $routes): void
    {
        foreach ($this->getBundles() as $bundle) {
            if ($bundle instanceof ShopwareBundle) {
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

    /**
     * @param array<int, string> $instanciatedBundleNames
     */
    private function isBundleRegistered(Bundle|ShopwareBundle $bundle, array $instanciatedBundleNames): bool
    {
        return \array_key_exists($bundle->getName(), $instanciatedBundleNames)
            || \array_key_exists($bundle->getName(), $this->bundles);
    }
}
