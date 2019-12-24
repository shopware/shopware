<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DriverManager;
use PackageVersions\Versions;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Profiling\Doctrine\DebugStack;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class HttpKernel implements HttpKernelInterface
{
    /**
     * @var Connection|null
     */
    protected static $connection;

    /**
     * @var ClassLoader|null
     */
    protected $classLoader;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var KernelPluginLoader|null
     */
    protected $pluginLoader;

    /**
     * @var KernelInterface|null
     */
    protected $kernel;

    public function __construct(string $environment, bool $debug, ?ClassLoader $classLoader = null)
    {
        $this->classLoader = $classLoader;
        $this->environment = $environment;
        $this->debug = $debug;
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        try {
            return $this->doHandle($request, (int) $type, (bool) $catch);
        } catch (ConnectionException $e) {
            $connection = self::getConnection();

            $message = str_replace([$connection->getParams()['password'], $connection->getParams()['user']], '******', $e->getMessage());

            throw new \RuntimeException(sprintf('Could not connect to database. Message from SQL Server: %s', $message));
        }
    }

    public function getKernel(): KernelInterface
    {
        return $this->createKernel();
    }

    /**
     * Allows to switch the plugin loading.
     */
    public function setPluginLoader(KernelPluginLoader $pluginLoader): void
    {
        $this->pluginLoader = $pluginLoader;
    }

    public static function getConnection(): Connection
    {
        if (self::$connection) {
            return self::$connection;
        }

        $url = $_ENV['DATABASE_URL']
            ?? $_SERVER['DATABASE_URL']
            ?? getenv('DATABASE_URL');

        $parameters = [
            'url' => $url,
            'charset' => 'utf8mb4',
        ];

        self::$connection = DriverManager::getConnection($parameters, new Configuration());

        return self::$connection;
    }

    private function doHandle(Request $request, int $type, bool $catch): Response
    {
        // create core kernel which contains bootstrapping for plugins etc.
        $kernel = $this->createKernel();
        $kernel->boot();

        $container = $kernel->getContainer();

        // transform request to resolve seo urls and detect sales channel
        $request = $container
            ->get(RequestTransformerInterface::class)
            ->transform($request);

        // check for http caching
        $enabled = $container->getParameter('shopware.http.cache.enabled');
        if ($enabled) {
            $kernel = new HttpCache($kernel, $container->get(CacheStore::class), null, ['debug' => $this->debug]);
        }

        $response = $kernel->handle($request, $type, $catch);

        // fire event to trigger runtime events like seo url headers
        $event = new BeforeSendResponseEvent($request, $response);
        $container->get('event_dispatcher')->dispatch($event);
        $response = $event->getResponse();

        // destroy http kernel
        $kernel->terminate($request, $response);

        return $response;
    }

    private function createKernel(): KernelInterface
    {
        if ($this->kernel !== null) {
            return $this->kernel;
        }

        $versions = Versions::VERSIONS;
        if (isset($versions['shopware/core'])) {
            $shopwareVersion = Versions::getVersion('shopware/core');
        } else {
            $shopwareVersion = Versions::getVersion('shopware/platform');
        }

        $connection = self::getConnection();

        if ($this->environment === 'dev') {
            $connection->getConfiguration()->setSQLLogger(new DebugStack());
        }

        $pluginLoader = $this->createPluginLoader($connection);

        $cacheId = (new CacheIdLoader($connection))->load();

        return $this->kernel = new Kernel(
            $this->environment,
            $this->debug,
            $pluginLoader,
            $cacheId,
            $shopwareVersion,
            $connection,
            $this->getProjectDir()
        );
    }

    private function getProjectDir()
    {
        if ($this->projectDir === null) {
            $r = new \ReflectionObject($this);

            if (!file_exists($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!file_exists($dir . '/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    private function createPluginLoader(Connection $connection): KernelPluginLoader
    {
        if ($this->pluginLoader) {
            return $this->pluginLoader;
        }

        if (!$this->classLoader) {
            throw new \RuntimeException('No plugin loader and no class loader provided');
        }

        $this->pluginLoader = new DbalKernelPluginLoader($this->classLoader, null, $connection);

        return $this->pluginLoader;
    }
}
