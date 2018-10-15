<?php declare(strict_types=1);

namespace Shopware\Core;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\BundleCollection;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
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
     * @var BundleCollection
     */
    protected static $plugins;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        self::$plugins = new BundleCollection();
        self::$connection = null;
    }

    public function registerBundles()
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';

        foreach (self::$plugins->getActives() as $plugin) {
            $contents[\get_class($plugin)] = ['all' => true];
        }

        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    public function boot($withPlugins = true)
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

        if ($withPlugins) {
            $this->initializePlugins();
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

    public static function getPlugins(): BundleCollection
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

    public function getCacheDir()
    {
        return sprintf(
            '%s/var/cache/%s_%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
            Framework::REVISION
        );
    }

    public function getLogDir()
    {
        return $this->getProjectDir() . '/var/logs';
    }

    public function getPluginDir(): string
    {
        return $this->getProjectDir() . '/custom/plugins';
    }

    public function shutdown()
    {
        if (!$this->booted) {
            return;
        }

        self::$plugins = new BundleCollection();
        self::$connection = null;

        return parent::shutdown();
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

        $this->addPluginRoutes($routes);
        $this->addApiRoutes($routes);
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

    protected function getContainerClass()
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
        $stmt = self::getConnection()->executeQuery('SELECT `name` FROM `plugin` WHERE `active` = 1 AND `installation_date` IS NOT NULL');
        $activePlugins = $stmt->fetchAll(FetchMode::COLUMN);

        $finder = new Finder();
        $iterator = $finder->directories()->depth(0)->in($this->getPluginDir())->getIterator();

        foreach ($iterator as $pluginDir) {
            $pluginName = $pluginDir->getFilename();
            $pluginFile = $pluginDir->getPath() . '/' . $pluginName . '/' . $pluginName . '.php';
            if (!is_file($pluginFile)) {
                continue;
            }

            $namespace = $pluginName;
            $className = '\\' . $namespace . '\\' . $pluginName;

            if (!class_exists($className)) {
                throw new \RuntimeException(
                    sprintf('Unable to load class %s for plugin %s in file %s', $className, $pluginName, $pluginFile)
                );
            }

            $isActive = \in_array($pluginName, $activePlugins, true);

            /** @var \Shopware\Core\Framework\Plugin $plugin */
            $plugin = new $className($isActive);

            if (!$plugin instanceof Plugin) {
                throw new \RuntimeException(
                    sprintf('Class %s must extend %s in file %s', \get_class($plugin), Plugin::class, $pluginFile)
                );
            }

            self::$plugins->add($plugin);
        }
    }

    private function addApiRoutes(RouteCollectionBuilder $routes): void
    {
        $class = ApiController::class;
        $uuidRegex = '.*\/[0-9a-f]{32}\/?$';

        // detail routes
        $route = new Route('/api/v{version}/{path}');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', $class . '::detail');
        $route->addRequirements(['path' => $uuidRegex, 'version' => '\d+']);
        $routes->addRoute($route, 'api_controller.detail');

        $route = new Route('/api/v{version}/{path}');
        $route->setMethods(['PATCH']);
        $route->setDefault('_controller', $class . '::update');
        $route->addRequirements(['path' => $uuidRegex, 'version' => '\d+']);
        $routes->addRoute($route, 'api_controller.update');

        $route = new Route('/api/v{version}/{path}');
        $route->setMethods(['DELETE']);
        $route->setDefault('_controller', $class . '::delete');
        $route->addRequirements(['path' => $uuidRegex, 'version' => '\d+']);
        $routes->addRoute($route, 'api_controller.delete');

        // list routes
        $route = new Route('/api/v{version}/{path}');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', $class . '::list');
        $route->addRequirements(['path' => '.*', 'version' => '\d+']);
        $routes->addRoute($route, 'api_controller.list');

        $route = new Route('/api/v{version}/search/{path}');
        $route->setMethods(['POST']);
        $route->setDefault('_controller', $class . '::search');
        $route->addRequirements(['path' => '.*', 'version' => '\d+']);
        $routes->addRoute($route, 'api_controller.search');

        $route = new Route('/api/v{version}/{path}');
        $route->setMethods(['POST']);
        $route->setDefault('_controller', $class . '::create');
        $route->addRequirements(['path' => '.*', 'version' => '\d+']);
        $routes->addRoute($route, 'api_controller.create');
    }

    private function addPluginRoutes(RouteCollectionBuilder $routes): void
    {
        foreach (static::$plugins->getActives() as $plugin) {
            $plugin->configureRoutes($routes, (string) $this->environment);
        }
    }

    private function initializeDatabaseConnectionVariables()
    {
        /** @var Connection $connection */
        $connection = $this->container->get('Doctrine\DBAL\Connection');

        $nonDestructiveMigrations = $connection->executeQuery('
            SELECT `creation_timestamp`
            FROM `migration`
            WHERE `update_destructive` IS NULL
        ')->fetchAll(\PDO::FETCH_COLUMN);
        $activeMigrations = $this->container->getParameter('migration.active');

        $activeNonDestructiveMigrations = array_intersect($activeMigrations, $nonDestructiveMigrations);

        foreach ($activeNonDestructiveMigrations as $migration) {
            $connectionVariables[] = sprintf(
                'SET %s = TRUE;',
                sprintf(MigrationStep::MIGRATION_VARIABLE_FORMAT, $migration)
            );
        }

        if (isset($connectionVariables)) {
            $connection->executeQuery(implode('; ', $connectionVariables));
        }
    }
}
