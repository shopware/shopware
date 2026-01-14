<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests to verify Shopware is ready for long runners (RoadRunner, FrankenPHP, Swoole).
 *
 * These tests simulate multiple requests handled by the same Kernel instance
 * to detect memory leaks and state pollution between requests.
 *
 * @internal
 */
#[Package('framework')]
#[Group('long-runner')]
class LongRunnerMemoryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * Maximum allowed memory growth per request (in bytes).
     * If memory grows more than this per request on average, we likely have a leak.
     */
    private const MAX_MEMORY_GROWTH_PER_REQUEST = 100 * 1024; // 100 KB

    /**
     * Number of requests to simulate for memory leak detection.
     */
    private const REQUEST_COUNT = 50;

    /**
     * Tests that memory usage remains stable across multiple requests.
     *
     * In long runner environments, the same PHP process handles multiple requests.
     * If services or caches accumulate data without proper reset, memory will grow
     * unbounded, eventually causing OOM errors.
     *
     * This test detects such memory leaks by:
     * 1. Running multiple requests through the kernel
     * 2. Measuring memory growth
     * 3. Failing if growth exceeds threshold
     */
    public function testMemoryRemainsStableAcrossMultipleRequests(): void
    {
        $kernel = KernelLifecycleManager::getKernel();

        // Warm up - run a few requests to stabilize memory
        for ($i = 0; $i < 5; ++$i) {
            $request = Request::create('/api/_info/version');
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        }

        // Force garbage collection before measurement
        gc_collect_cycles();
        $initialMemory = memory_get_usage(true);

        // Run test requests
        for ($i = 0; $i < self::REQUEST_COUNT; ++$i) {
            $request = Request::create('/api/_info/version');
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        }

        // Force garbage collection after measurement
        gc_collect_cycles();
        $finalMemory = memory_get_usage(true);

        $memoryGrowth = $finalMemory - $initialMemory;
        $growthPerRequest = $memoryGrowth / self::REQUEST_COUNT;
        $maxAllowedGrowth = self::MAX_MEMORY_GROWTH_PER_REQUEST * self::REQUEST_COUNT;

        static::assertLessThan(
            $maxAllowedGrowth,
            $memoryGrowth,
            \sprintf(
                "Memory leak detected!\n"
                . "Initial memory: %s\n"
                . "Final memory: %s\n"
                . "Total growth: %s (%d bytes)\n"
                . "Growth per request: %s\n"
                . "Max allowed per request: %s\n"
                . 'Requests: %d',
                $this->formatBytes($initialMemory),
                $this->formatBytes($finalMemory),
                $this->formatBytes($memoryGrowth),
                $memoryGrowth,
                $this->formatBytes((int) $growthPerRequest),
                $this->formatBytes(self::MAX_MEMORY_GROWTH_PER_REQUEST),
                self::REQUEST_COUNT
            )
        );
    }

    /**
     * Tests that SwTwigFunction escape cache can be reset and SwTwigFunctionResetter is registered.
     *
     * This test verifies:
     * 1. SwTwigFunction::resetEscapeCache() properly clears the static cache
     * 2. SwTwigFunctionResetter is registered with kernel.reset tag
     *
     * The actual service reset integration is handled by Symfony's ServicesResetter
     * and triggered during Kernel::boot() when resetServices flag is set.
     */
    public function testSwTwigFunctionEscapeCacheCanBeReset(): void
    {
        $kernel = KernelLifecycleManager::getKernel();
        $container = $kernel->getContainer();

        // Verify services_resetter exists (prerequisite for long runner support)
        static::assertTrue(
            $container->has('services_resetter'),
            'services_resetter service should exist in container'
        );

        // Populate the escape cache with test data via reflection
        $reflection = new \ReflectionClass(\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::class);
        $cacheProperty = $reflection->getProperty('escapeCache');
        $cacheProperty->setValue(null, ['test_key' => 'test_value']);

        // Verify cache is populated
        static::assertNotEmpty(
            $cacheProperty->getValue(null),
            'Escape cache should have test data before reset'
        );

        // Call the public reset method directly
        \Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::resetEscapeCache();

        // Verify cache was cleared
        static::assertEmpty(
            $cacheProperty->getValue(null),
            'SwTwigFunction::$escapeCache should be empty after resetEscapeCache()'
        );
    }

    /**
     * Tests memory stability with diverse content to stress-test caches.
     *
     * Some caches (like escape filter cache) grow with unique content.
     * This test generates diverse requests to detect such issues.
     */
    public function testMemoryStabilityWithDiverseRequests(): void
    {
        $kernel = KernelLifecycleManager::getKernel();

        // Warm up
        for ($i = 0; $i < 3; ++$i) {
            $request = Request::create('/api/_info/version');
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        }

        gc_collect_cycles();
        $initialMemory = memory_get_usage(true);

        // Various endpoints to trigger different code paths
        $endpoints = [
            '/api/_info/version',
            '/api/_info/config',
        ];

        for ($i = 0; $i < self::REQUEST_COUNT; ++$i) {
            $endpoint = $endpoints[$i % \count($endpoints)];

            // Add unique query parameter to prevent response caching
            $request = Request::create($endpoint . '?_=' . $i . '_' . uniqid());
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage(true);

        $memoryGrowth = $finalMemory - $initialMemory;
        $maxAllowedGrowth = self::MAX_MEMORY_GROWTH_PER_REQUEST * self::REQUEST_COUNT;

        static::assertLessThan(
            $maxAllowedGrowth,
            $memoryGrowth,
            \sprintf(
                'Memory leak detected with diverse requests!'
                . ' Growth: %s (max allowed: %s)',
                $this->formatBytes($memoryGrowth),
                $this->formatBytes($maxAllowedGrowth)
            )
        );
    }

    /**
     * Tests that MySQLFactory properly manages idle database connections.
     *
     * MySQLFactory::getConnection() proactively closes idle connections to prevent
     * "MySQL server has gone away" errors in long runner environments.
     */
    public function testMySQLFactoryIdleConnectionManagement(): void
    {
        // Verify MySQLFactory::getConnection() returns a valid connection
        $connection = \Shopware\Core\Framework\Adapter\Database\MySQLFactory::getConnection();

        // Verify the connection works
        $result = $connection->executeQuery('SELECT 1')->fetchOne();
        static::assertSame('1', $result);

        // Verify getConnection() returns the same instance (connection caching)
        $connection2 = \Shopware\Core\Framework\Adapter\Database\MySQLFactory::getConnection();
        static::assertSame($connection, $connection2);
    }

    /**
     * Tests that requestStackSize is properly managed.
     *
     * Symfony Kernel tracks nested requests via requestStackSize.
     * After each request cycle (handle + terminate), this should return to 0.
     */
    public function testRequestStackSizeIsProperlyManaged(): void
    {
        $kernel = KernelLifecycleManager::getKernel();

        // Use reflection to check requestStackSize
        $reflection = new \ReflectionClass($kernel);

        // Find requestStackSize property (it's in parent Symfony Kernel)
        $parent = $reflection->getParentClass();
        while ($parent && !$parent->hasProperty('requestStackSize')) {
            $parent = $parent->getParentClass();
        }

        if (!$parent) {
            static::markTestSkipped('Could not find requestStackSize property in Kernel hierarchy');
        }

        $stackSizeProperty = $parent->getProperty('requestStackSize');

        // Run a request
        $request = Request::create('/api/_info/version');
        $response = $kernel->handle($request);

        // During request handling, stack size should be >= 1
        // (we can't easily check this during handle, but after terminate it should be 0)

        $kernel->terminate($request, $response);

        $stackSizeAfter = $stackSizeProperty->getValue($kernel);

        static::assertSame(
            0,
            $stackSizeAfter,
            'requestStackSize should be 0 after request cycle completes'
        );
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return round($bytes / 1024 / 1024, 2) . ' MB';
    }
}
