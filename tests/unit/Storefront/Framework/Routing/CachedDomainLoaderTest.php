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
        $decorated = new CountingDomainLoader();
        $cache = new CountingArrayAdapter();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertCount(1, $loader->loadDomains());
        static::assertCount(1, $loader->loadDomains());

        static::assertSame(1, $cache->getCalls);
        static::assertSame(1, $decorated->loadDomainsCalls);
    }
}

/**
 * @internal
 */
class CountingArrayAdapter extends ArrayAdapter
{
    public int $getCalls = 0;

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        ++$this->getCalls;

        return parent::get($key, $callback, $beta, $metadata);
    }
}

/**
 * @internal
 */
class CountingDomainLoader extends AbstractDomainLoader
{
    public int $loadDomainsCalls = 0;

    public function getDecorated(): AbstractDomainLoader
    {
        throw new \BadMethodCallException('This test loader does not decorate another loader.');
    }

    public function load(): array
    {
        throw new \BadMethodCallException('This test uses loadDomains().');
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
}
