<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Routing\AbstractDomainLoader;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CachedDomainLoader::class)]
class CachedDomainLoaderTest extends TestCase
{
    public function testCachesDomainCollectionInMemory(): void
    {
        $decorated = $this->createDomainLoader();
        $cache = $this->createCache();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertCount(1, $loader->loadDomains());
        static::assertCount(1, $loader->loadDomains());

        static::assertSame(1, $cache->getCalls);
        static::assertSame(1, $decorated->loadDomainsCalls);
    }

    public function testResetClearsDomainCollectionCache(): void
    {
        $decorated = $this->createDomainLoader();
        $cache = $this->createCache();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertCount(1, $loader->loadDomains());

        $loader->reset();

        // After reset() memory is cleared — persistent cache is hit but inner loader is not called again
        static::assertCount(1, $loader->loadDomains());

        static::assertSame(2, $cache->getCalls);
        static::assertSame(1, $decorated->loadDomainsCalls);
    }

    /**
     * @deprecated tag:v6.8.0 - Tests deprecated load(), remove when load() is removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCachesDomainsInMemory(): void
    {
        $decorated = $this->createDomainLoaderWithLoadCounter();
        $cache = $this->createCache();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertSame([], $loader->load());
        // Second call must return from memory without hitting the cache or inner loader
        static::assertSame([], $loader->load());

        static::assertSame(1, $cache->getCalls);
        static::assertSame(1, $decorated->loadCalls);
    }

    /**
     * @return ArrayAdapter&object{getCalls: int}
     */
    private function createCache(): ArrayAdapter
    {
        return new class extends ArrayAdapter {
            public int $getCalls = 0;

            /**
             * @phpstan-ignore missingType.iterableValue (array type needs to be defined on external dependency)
             */
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                ++$this->getCalls;

                return parent::get($key, $callback, $beta, $metadata);
            }
        };
    }

    /**
     * @return AbstractDomainLoader&object{loadDomainsCalls: int}
     */
    private function createDomainLoader(): AbstractDomainLoader
    {
        return new class extends AbstractDomainLoader {
            public int $loadDomainsCalls = 0;

            public function getDecorated(): AbstractDomainLoader
            {
                return $this;
            }

            public function load(): array
            {
                return [];
            }

            public function loadDomains(): DomainCollection
            {
                ++$this->loadDomainsCalls;

                return DomainCollection::fromArray([
                    'https://example.com/' => [
                        'url' => 'https://example.com',
                        'id' => 'domain-id',
                        'salesChannelId' => 'sales-channel-id',
                        'typeId' => 'type-id',
                        'snippetSetId' => 'snippet-set-id',
                        'currencyId' => 'currency-id',
                        'languageId' => 'language-id',
                        'themeId' => 'theme-id',
                        'maintenance' => '0',
                        'maintenanceIpAllowlist' => null,
                        'locale' => 'en-GB',
                        'themeName' => 'Storefront',
                        'parentThemeName' => null,
                    ],
                ]);
            }
        };
    }

    /**
     * @return AbstractDomainLoader&object{loadCalls: int}
     */
    private function createDomainLoaderWithLoadCounter(): AbstractDomainLoader
    {
        return new class extends AbstractDomainLoader {
            public int $loadCalls = 0;

            public function getDecorated(): AbstractDomainLoader
            {
                return $this;
            }

            public function load(): array
            {
                ++$this->loadCalls;

                return [];
            }

            public function loadDomains(): DomainCollection
            {
                return DomainCollection::fromArray([]);
            }
        };
    }
}
