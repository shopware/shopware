<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileSourceLoader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileSourceLoader::class)]
class SalesChannelFileSourceLoaderTest extends TestCase
{
    public function testLoadReturnsShopwareSourcesWithoutRepositoryLookup(): void
    {
        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([]);
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $sources = (new SalesChannelFileSourceLoader($pluginRepository, $appRepository))->load(
            ['Framework', 'Framework', 'Storefront'],
            Context::createDefaultContext()
        );

        static::assertSame([
            'Framework' => [
                'sourceName' => 'Shopware',
                'sourceType' => 'shopware',
                'sourceIcon' => null,
            ],
            'Storefront' => [
                'sourceName' => 'Shopware',
                'sourceType' => 'shopware',
                'sourceIcon' => null,
            ],
        ], $sources);
    }

    public function testLoadResolvesPluginAppAndBundleFallbackSources(): void
    {
        $context = Context::createDefaultContext();
        $plugin = $this->createPlugin('SwagAgentic', 'Agentic Plugin', 'plugin-icon');
        $app = $this->createApp('AgenticApp', '', 'app-icon');

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([
            function (Criteria $criteria, Context $searchContext) use ($context, $plugin): PluginCollection {
                static::assertSame($context, $searchContext);
                $this->assertEqualsAnyFilter($criteria, ['SwagAgentic', 'AgenticApp', 'SymfonyBundle']);

                return new PluginCollection([$plugin]);
            },
        ]);
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            function (Criteria $criteria, Context $searchContext) use ($context, $app): AppCollection {
                static::assertSame($context, $searchContext);
                $this->assertEqualsAnyFilter($criteria, ['AgenticApp', 'SymfonyBundle']);

                return new AppCollection([$app]);
            },
        ]);

        $sources = (new SalesChannelFileSourceLoader($pluginRepository, $appRepository))->load(
            ['Framework', 'SwagAgentic', 'AgenticApp', 'SymfonyBundle'],
            $context
        );

        static::assertSame([
            'Framework' => [
                'sourceName' => 'Shopware',
                'sourceType' => 'shopware',
                'sourceIcon' => null,
            ],
            'SwagAgentic' => [
                'sourceName' => 'Agentic Plugin',
                'sourceType' => 'plugin',
                'sourceIcon' => 'plugin-icon',
            ],
            'AgenticApp' => [
                'sourceName' => 'AgenticApp',
                'sourceType' => 'app',
                'sourceIcon' => 'app-icon',
            ],
            'SymfonyBundle' => [
                'sourceName' => 'SymfonyBundle',
                'sourceType' => 'bundle',
                'sourceIcon' => null,
            ],
        ], $sources);
    }

    /**
     * @param list<string> $expectedValues
     */
    private function assertEqualsAnyFilter(Criteria $criteria, array $expectedValues): void
    {
        $filters = $criteria->getFilters();

        static::assertCount(1, $filters);
        $filter = $filters[0];
        static::assertInstanceOf(EqualsAnyFilter::class, $filter);
        static::assertSame('name', $filter->getField());
        static::assertSame($expectedValues, $filter->getValue());
    }

    private function createPlugin(string $name, string $label, string $icon): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName($name);
        $plugin->setTranslated(['label' => $label]);
        $plugin->setIcon($icon);

        return $plugin;
    }

    private function createApp(string $name, string $label, string $icon): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName($name);
        $app->setTranslated(['label' => $label]);
        $app->setIcon($icon);

        return $app;
    }
}
