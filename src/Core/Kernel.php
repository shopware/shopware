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
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\VersionParser;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Route;

#[Package('framework')]
class Kernel extends HttpKernel
{
    use MicroKernelTrait;

    final public const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    /**
     * @var string Fallback version if nothing is provided via kernel constructor
     */
    final public const SHOPWARE_FALLBACK_VERSION = '6.7.9999999-dev';

    protected static ?Connection $connection = null;

    protected string $shopwareVersion;

    protected ?string $shopwareVersionRevision;

    private bool $rebooting = false;

    private string $cacheRootDir;

    /**
     * @internal
     */
    public function __construct(
        string $environment,
        bool $debug,
        protected KernelPluginLoader $pluginLoader,
        private string $cacheId,
        string $version,
        Connection $connection,
        protected string $projectDir,
    ) {
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
        self::$connection = $connection;

        $versionArray = VersionParser::parseShopwareVersion($version);
        $this->shopwareVersion = $versionArray['version'];
        $this->shopwareVersionRevision = $versionArray['revision'];

        $this->cacheRootDir = EnvironmentHelper::getVariable('APP_CACHE_DIR', $this->getProjectDir()) . '/var/cache';
    }

    public function registerBundles(): iterable
    {
        /** @var array<class-string<Bundle>, array<string, bool>> $bundles */
        $bundles = require $this->getBundlesPath();
        $instantiatedBundleNames = [];

        $kernelParameters = $this->getKernelParameters();

        foreach ($bundles as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                $bundle = new $class();

                if ($this->isBundleRegistered($bundle, $instantiatedBundleNames)) {
                    continue;
                }

                $instantiatedBundleNames[] = $bundle->getName();

                yield $bundle;

                if (!$bundle instanceof ShopwareBundle) {
                    continue;
                }

                $classLoader = new ClassLoader();
                $parameters = new AdditionalBundleParameters($classLoader, new KernelPluginCollection(), $kernelParameters);
                foreach ($bundle->getAdditionalBundles($parameters) as $additionalBundle) {
                    if ($this->isBundleRegistered($additionalBundle, $instantiatedBundleNames)) {
                        continue;
                    }

                    $instantiatedBundleNames[] = $additionalBundle->getName();
                    yield $additionalBundle;
                }
            }
        }

        yield from $this->pluginLoader->getBundles($kernelParameters, $instantiatedBundleNames);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        $this->boot();

        return $this->getHttpKernel()->handle($request, $type, $catch);
    }

    public function boot(): void
    {
        if (!$this->booted) {
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
        }

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
            '%s/%s_h%s',
            $this->cacheRootDir,
            $this->getEnvironment(),
            $this->getCacheHash(),
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
     * @return array<string, array<string, mixed>|bool|string|int|float|\UnitEnum|null>
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

        asort($plugins);

        return Hasher::hash([
            $this->cacheId,
            (string) $this->shopwareVersionRevision,
            $plugins,
        ]);
    }

    /**
     * @deprecated tag:v6.8.0 - removed: all connection variables are configured in MySQLFactory
     */
    protected function initializeDatabaseConnectionVariables(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'The method initializeDatabaseConnectionVariables is deprecated and will be removed in 6.8.0.0. All MySQL connection variables are configured in ' . MySQLFactory::class
        );

        self::$connection = self::getConnection();
    }

    protected function dumpContainer(ConfigCache $cache, ContainerBuilder $container, string $class, string $baseClass): void
    {
        parent::dumpContainer($cache, $container, $class, $baseClass);

        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->cacheRootDir . \DIRECTORY_SEPARATOR . 'CACHEDIR.TAG', 'Signature: 8a477f597d28d172789f06886806bc55');

        $cacheDir = $container->getParameter('kernel.cache_dir');

        // Do not dump the preload file if the cache dir is a warmup dir.
        // See https://github.com/symfony/symfony/blob/v7.2.6/src/Symfony/Bundle/FrameworkBundle/Command/CacheClearCommand.php#L115-L117
        if (str_ends_with($cacheDir, '_')) {
            return;
        }

        $cacheDirectoryName = basename($cacheDir);
        $containerPreloadFileName = $class . '.preload.php';

        $preloadFileContent = <<<PHP
<?php

require_once __DIR__ . '/#CACHE_PATH#';
PHP;

        // Dumps the preload file to an always known location outside the generated cache folder name
        $filesystem->dumpFile(
            $this->cacheRootDir . \DIRECTORY_SEPARATOR . 'opcache-preload.php',
            str_replace(
                '#CACHE_PATH#',
                $cacheDirectoryName . \DIRECTORY_SEPARATOR . $containerPreloadFileName,
                $preloadFileContent,
            )
        );
    }

    private function addApiRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', ApiRouteScope::ID);
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
     * @param array<int, string> $instantiatedBundleNames
     */
    private function isBundleRegistered(Bundle|ShopwareBundle $bundle, array $instantiatedBundleNames): bool
    {
        return \array_key_exists($bundle->getName(), $instantiatedBundleNames)
            || \array_key_exists($bundle->getName(), $this->bundles);
    }
}
