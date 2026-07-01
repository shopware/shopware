<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\AbstractDomainLoader;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(CachedDomainLoader::class)]
#[Package('framework')]
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

    /**
     * @return ArrayAdapter&object{getCalls: int}
     */
    private function createCache(): ArrayAdapter
    {
        return new class extends ArrayAdapter {
            public int $getCalls = 0;

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
}
