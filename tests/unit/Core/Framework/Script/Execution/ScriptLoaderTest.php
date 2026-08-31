<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptLoader::class)]
class ScriptLoaderTest extends TestCase
{
    public function testCachesLoadedScriptsInMemory(): void
    {
        $cache = $this->createCache();
        $loader = new ScriptLoader(
            $this->createConnection(),
            static::createStub(ScriptLifecycleHandler::class),
            $cache,
            sys_get_temp_dir(),
            false,
        );

        static::assertCount(1, $loader->get('first-hook'));
        static::assertCount(1, $loader->get('second-hook'));

        static::assertSame(1, $cache->getItemCalls);
    }

    public function testLoadsFromPersistentCacheAfterReset(): void
    {
        $cache = $this->createCache();
        $loader = new ScriptLoader(
            $this->createConnection(),
            static::createStub(ScriptLifecycleHandler::class),
            $cache,
            sys_get_temp_dir(),
            false,
        );

        static::assertCount(1, $loader->get('first-hook'));

        $loader->reset();

        // Second get() must hit persistent cache, not DB — connection mock enforces once()
        static::assertCount(1, $loader->get('first-hook'));

        static::assertSame(2, $cache->getItemCalls);
    }

    public function testInvalidateCacheClearsMemoryAndPersistentCache(): void
    {
        $cache = $this->createCache();
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([$this->createScriptInfo('first-hook')]);

        $loader = new ScriptLoader(
            $connection,
            static::createStub(ScriptLifecycleHandler::class),
            $cache,
            sys_get_temp_dir(),
            false,
        );

        static::assertCount(1, $loader->get('first-hook'));

        $loader->invalidateCache();

        // After invalidation both memory and persistent cache are cleared — DB must be hit again
        static::assertCount(1, $loader->get('first-hook'));

        static::assertSame(2, $cache->getItemCalls);
    }

    /**
     * @return TagAwareAdapter&object{getItemCalls: int}
     */
    private function createCache(): TagAwareAdapter
    {
        return new class extends TagAwareAdapter {
            public int $getItemCalls = 0;

            public function __construct()
            {
                parent::__construct(new ArrayAdapter());
            }

            public function getItem(mixed $key): CacheItem
            {
                ++$this->getItemCalls;

                return parent::getItem($key);
            }
        };
    }

    private function createConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                $this->createScriptInfo('first-hook'),
                $this->createScriptInfo('second-hook'),
            ]);

        return $connection;
    }

    /**
     * @return array{app_id: string, scriptName: string, script: string, hook: string, appName: string, appVersion: string, integrationId: string, lastModified: string, active: string}
     */
    private function createScriptInfo(string $hook): array
    {
        return [
            'app_id' => '018f89c8a0cf70b5ac102d19a804db18',
            'scriptName' => $hook,
            'script' => '',
            'hook' => $hook,
            'appName' => 'TestApp',
            'appVersion' => '1.0.0',
            'integrationId' => '018f89c8a0cf70b5ac102d19a804db19',
            'lastModified' => '2024-01-01 00:00:00.000',
            'active' => '1',
        ];
    }
}
