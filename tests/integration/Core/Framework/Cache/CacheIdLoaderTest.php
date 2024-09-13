<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Cache;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('cache')]
class CacheIdLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CacheIdLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = $this->getContainer()->get(CacheIdLoader::class);
        unset($_SERVER['SHOPWARE_CACHE_ID']);
    }

    public function testCacheIdIsLoadedFromDatabase(): void
    {
        $old = $this->loader->load();

        static::assertTrue(Uuid::isValid($old));

        $new = Uuid::randomHex();

        $this->getContainer()->get(AbstractKeyValueStorage::class)->set('cache-id', $new);

        static::assertSame($new, $this->loader->load());

        $this->loader->write($old);

        static::assertSame($old, $this->loader->load());
    }
}
