<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Shopware\Core\System\SystemConfig\CachedSystemConfigLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedSystemConfigLoader::class)]
class CachedSystemConfigLoaderTest extends TestCase
{
    /**
     * @var list<string|null>
     */
    private array $loadedSalesChannelIds = [];

    private TagAwareAdapter $cache;

    private CachedSystemConfigLoader $loader;

    protected function setUp(): void
    {
        $decorated = static::createStub(AbstractSystemConfigLoader::class);
        $decorated->method('load')->willReturnCallback(function (?string $salesChannelId): array {
            $this->loadedSalesChannelIds[] = $salesChannelId;

            return ['core' => ['basicInformation' => ['shopName' => 'Shop ' . $salesChannelId]]];
        });

        $this->cache = new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter());
        $this->loader = new CachedSystemConfigLoader($decorated, $this->cache);
    }

    public function testLoadsTheConfigOncePerSalesChannel(): void
    {
        static::assertSame(['core' => ['basicInformation' => ['shopName' => 'Shop sales-channel-a']]], $this->loader->load('sales-channel-a'));
        static::assertSame(['core' => ['basicInformation' => ['shopName' => 'Shop sales-channel-a']]], $this->loader->load('sales-channel-a'));
        static::assertSame(['core' => ['basicInformation' => ['shopName' => 'Shop ']]], $this->loader->load(null));
        static::assertSame(['core' => ['basicInformation' => ['shopName' => 'Shop ']]], $this->loader->load(null));

        static::assertSame(['sales-channel-a', null], $this->loadedSalesChannelIds);
    }

    public function testTheSalesChannelTagDropsOnlyTheConfigOfThatSalesChannel(): void
    {
        $this->loadAll();
        $this->loadedSalesChannelIds = [];

        $this->cache->invalidateTags([CachedSystemConfigLoader::buildSalesChannelTag('sales-channel-a')]);
        $this->loadAll();

        static::assertSame(['sales-channel-a'], $this->loadedSalesChannelIds);
    }

    public function testTheGlobalTagDropsTheConfigOfAllSalesChannels(): void
    {
        $this->loadAll();
        $this->loadedSalesChannelIds = [];

        $this->cache->invalidateTags([CachedSystemConfigLoader::CACHE_TAG]);
        $this->loadAll();

        static::assertSame([null, 'sales-channel-a', 'sales-channel-b'], $this->loadedSalesChannelIds);
    }

    private function loadAll(): void
    {
        $this->loader->load(null);
        $this->loader->load('sales-channel-a');
        $this->loader->load('sales-channel-b');
    }
}
