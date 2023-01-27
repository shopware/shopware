<?php declare(strict_types=1);

namespace Shopware\Core;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Event\BeforeSendRedirectResponseEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Routing\CanonicalRedirectService;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @psalm-import-type Params from DriverManager
 */
#[Package('core')]
class HttpKernel
{
    protected static ?Connection $connection = null;

    /**
     * @var class-string<Kernel>
     */
    protected static string $kernelClass = Kernel::class;

    /**
     * @var class-string<HttpCache>
     */
    protected static string $httpCacheClass = HttpCache::class;

    protected ?string $projectDir = null;

    protected ?KernelPluginLoader $pluginLoader = null;

    protected ?KernelInterface $kernel = null;

    public function __construct(
        protected string $environment,
        protected bool $debug,
        protected ?ClassLoader $classLoader = null
    ) {
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): HttpKernelResult
    {
        try {
            return $this->doHandle($request, $type, $catch);
        } catch (Exception $e) {
            /** @var Params|array{url?: string} $connectionParams */
            $connectionParams = self::getConnection()->getParams();

            $message = str_replace([$connectionParams['url'] ?? null, $connectionParams['password'] ?? null, $connectionParams['user'] ?? null], '******', $e->getMessage());

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

    /**
     * @param array<Middleware> $middlewares
     */
    public static function getConnection(array $middlewares = []): Connection
    {
        if (self::$connection) {
            return self::$connection;
        }

        self::$connection = MySQLFactory::create($middlewares);

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
        if ($enabled && $container->has(CacheStore::class)) {
            $kernel = new static::$httpCacheClass($kernel, $container->get(CacheStore::class), null, ['debug' => $this->debug]);
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

        $middlewares = [];
        if (InstalledVersions::isInstalled('symfony/doctrine-bridge')) {
            $middlewares = [new ProfilingMiddleware()];
        }

        $connection = self::getConnection($middlewares);

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

    private function getProjectDir(): string
    {
        if ($this->projectDir === null) {
            if ($dir = $_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? false) {
                return $this->projectDir = $dir;
            }

            $r = new \ReflectionObject($this);

            /** @var string $dir */
            $dir = $r->getFileName();
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
