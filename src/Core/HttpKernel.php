<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Event\BeforeSendRedirectResponseEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Routing\CanonicalRedirectService;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Profiling\Doctrine\DebugStack;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class HttpKernel
{
    /**
     * @var Connection|null
     */
    protected static $connection;

    /**
     * @var class-string<Kernel>
     */
    protected static $kernelClass = Kernel::class;

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

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true): HttpKernelResult
    {
        try {
            return $this->doHandle($request, (int) $type, (bool) $catch);
        } catch (DBALException $e) {
            $connectionParams = self::getConnection()->getParams();

            $message = str_replace([$connectionParams['url'], $connectionParams['password'], $connectionParams['user']], '******', $e->getMessage());

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

        return self::$connection;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!$this->kernel instanceof TerminableInterface) {
            return;
        }

        $this->kernel->terminate($request, $response);
    }

    private function doHandle(Request $request, int $type, bool $catch): HttpKernelResult
    {
        // create core kernel which contains bootstrapping for plugins etc.
        $kernel = $this->createKernel();
        $kernel->boot();

        $container = $kernel->getContainer();

        // transform request to resolve seo urls and detect sales channel
        $transformed = $container
            ->get(RequestTransformerInterface::class)
            ->transform($request);

        $redirect = $container
            ->get(CanonicalRedirectService::class)
            ->getRedirect($transformed);

        if ($redirect instanceof RedirectResponse) {
            $event = new BeforeSendRedirectResponseEvent($transformed, $redirect);
            $container->get('event_dispatcher')->dispatch($event);

            return new HttpKernelResult($transformed, $event->getResponse());
        }

        // check for http caching
        $enabled = $container->hasParameter('shopware.http.cache.enabled')
            && $container->getParameter('shopware.http.cache.enabled');
        if ($enabled) {
            $kernel = new HttpCache($kernel, $container->get(CacheStore::class), null, ['debug' => $this->debug]);
        }

        $response = $kernel->handle($transformed, $type, $catch);

        // fire event to trigger runtime events like seo url headers
        $event = new BeforeSendResponseEvent($transformed, $response);
        $container->get('event_dispatcher')->dispatch($event);

        return new HttpKernelResult($transformed, $event->getResponse());
    }

    private function createKernel(): KernelInterface
    {
        if ($this->kernel !== null) {
            return $this->kernel;
        }

        if (InstalledVersions::isInstalled('shopware/platform')) {
            $shopwareVersion = InstalledVersions::getVersion('shopware/platform')
                . '@' . InstalledVersions::getReference('shopware/platform');
        } else {
            $shopwareVersion = InstalledVersions::getVersion('shopware/core')
                . '@' . InstalledVersions::getReference('shopware/core');
        }

        $connection = self::getConnection();

        if ($this->environment === 'dev') {
            $connection->getConfiguration()->setSQLLogger(new DebugStack());
        }

        $pluginLoader = $this->createPluginLoader($connection);

        $cacheId = (new CacheIdLoader($connection))->load();

        return $this->kernel = new static::$kernelClass(
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
