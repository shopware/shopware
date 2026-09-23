<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Covers the artifacts `Shopware\Core\Kernel::dumpContainer()` writes next to the generated cache
 * folder. Both tests compile a real container, so they redirect the cache root away from the
 * project's `var/cache` via `APP_CACHE_DIR` and boot their own kernel.
 *
 * @internal
 */
#[Package('framework')]
class KernelTest extends TestCase
{
    use EnvTestBehaviour;

    private string $appCacheDir;

    /**
     * `Kernel` derives this from `APP_CACHE_DIR` and writes both artifacts directly into it.
     */
    private string $cacheRootDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->appCacheDir = sys_get_temp_dir() . '/shopware-kernel-test-' . bin2hex(random_bytes(8));
        $this->cacheRootDir = $this->appCacheDir . '/var/cache';

        $this->setEnvVars(['APP_CACHE_DIR' => $this->appCacheDir]);
    }

    protected function tearDown(): void
    {
        // without this the redirected APP_CACHE_DIR leaks into every later test in the same process
        $this->resetEnvVars();
        $this->filesystem->remove($this->appCacheDir);
    }

    public function testBootDumpsCacheDirTagAndOpcachePreloadFile(): void
    {
        $kernel = KernelLifecycleManager::createKernel();

        try {
            $kernel->boot();

            static::assertFileExists($this->cacheRootDir . '/CACHEDIR.TAG');

            // the dumped container lives in a `ContainerXXXXXXX` sub namespace, its short name is the container class
            $containerClass = basename(str_replace('\\', '/', $kernel->getContainer()::class));
            $preloadedFile = basename($kernel->getCacheDir()) . '/' . $containerClass . '.preload.php';

            static::assertSame(
                "<?php\n\nrequire_once __DIR__ . '/" . $preloadedFile . '\';',
                (string) file_get_contents($this->cacheRootDir . '/opcache-preload.php')
            );

            // `__DIR__` of the preload file is the cache root, so the relative require must resolve
            static::assertFileExists($this->cacheRootDir . '/' . $preloadedFile);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testBootIntoAWarmupCacheDirSkipsTheOpcachePreloadFile(): void
    {
        $kernel = KernelLifecycleManager::createKernel();

        try {
            // `reboot()` is how `cache:clear` warms up a container: the trailing underscore marks the
            // build directory as a warmup directory and becomes the `kernel.cache_dir` parameter
            $kernel->reboot($kernel->getCacheDir() . '_');

            static::assertFileExists($this->cacheRootDir . '/CACHEDIR.TAG');
            static::assertFileDoesNotExist($this->cacheRootDir . '/opcache-preload.php');
        } finally {
            $kernel->shutdown();
        }
    }
}
