<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlRouteLoader::class)]
class AppSeoUrlRouteLoaderTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_APP_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private TagAwareAdapterInterface $cache;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->cache = new TagAwareAdapter(new ArrayAdapter());

        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );
    }

    public function testLoadYieldsAnEntityRouteForEveryKnownEntity(): void
    {
        $loader = $this->createLoader([
            $this->entityRouteRow('product-teaser', 'product'),
            $this->entityRouteRow('blog-detail', 'ce_blog'),
        ]);

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertInstanceOf(ConfiguredEntitySeoUrlRoute::class, $routes[0]);

        $config = $routes[0]->getConfig();
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame('{{ product.translated.name }}', $config->getTemplate());
        static::assertSame('product', $config->getDefinition()->getEntityName());
        static::assertSame(['hook' => 'product-teaser', 'id' => 'the-id'], $config->getPrimaryKeyParameter('the-id'));
    }

    public function testLoadSkipsStaticRoutes(): void
    {
        $loader = $this->createLoader([
            $this->staticRouteRow('imprint', ['en-GB' => 'imprint']),
        ]);

        static::assertSame([], iterator_to_array($loader->load(), false));
    }

    public function testStaticAndEntityRoutesAreSeparated(): void
    {
        $loader = $this->createLoader([
            $this->staticRouteRow('imprint', ['en-GB' => 'imprint', 'de-DE' => 'impressum']),
            $this->entityRouteRow('product-teaser', 'product'),
        ]);

        static::assertSame([[
            'appId' => self::APP_ID,
            'routeName' => 'storefront.app.SwagSeoUrlApp.imprint',
            'hook' => 'imprint',
            'paths' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        ]], $loader->getStaticRoutes());

        static::assertSame([[
            'appId' => self::APP_ID,
            'routeName' => 'storefront.app.SwagSeoUrlApp.product-teaser',
            'hook' => 'product-teaser',
            'entityName' => 'product',
            'defaultTemplate' => '{{ product.translated.name }}',
        ]], $loader->getEntityRoutes());
    }

    public function testStaticRoutesWithoutAnyPathAreIgnored(): void
    {
        $loader = $this->createLoader([
            $this->staticRouteRow('imprint', null),
        ]);

        static::assertSame([], $loader->getStaticRoutes());
    }

    public function testRoutesCanBeFilteredByApp(): void
    {
        $loader = $this->createLoader([
            $this->staticRouteRow('imprint', ['en-GB' => 'imprint']),
            $this->entityRouteRow('product-teaser', 'product'),
            ['appId' => self::OTHER_APP_ID] + $this->staticRouteRow('other-imprint', ['en-GB' => 'other-imprint']),
            ['appId' => self::OTHER_APP_ID] + $this->entityRouteRow('other-teaser', 'product'),
        ]);

        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.imprint'],
            array_column($loader->getStaticRoutes(self::APP_ID), 'routeName')
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.product-teaser'],
            array_column($loader->getEntityRoutes(self::APP_ID), 'routeName')
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.other-imprint'],
            array_column($loader->getStaticRoutes(self::OTHER_APP_ID), 'routeName')
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.other-teaser'],
            array_column($loader->getEntityRoutes(self::OTHER_APP_ID), 'routeName')
        );
    }

    public function testTheDatabaseIsQueriedOnlyOnceWhileTheRoutesAreMemoised(): void
    {
        $queries = 0;
        $loader = $this->createLoader([$this->staticRouteRow('imprint', ['en-GB' => 'imprint'])], $queries);

        $loader->getStaticRoutes();
        $loader->getEntityRoutes();
        $loader->getStaticRoutes();

        static::assertSame(1, $queries);
    }

    public function testASecondLoaderReadsTheRoutesFromTheSharedCache(): void
    {
        $this->createLoader([$this->staticRouteRow('imprint', ['en-GB' => 'imprint'])])->getStaticRoutes();

        $queries = 0;
        $cold = $this->createLoader([], $queries);

        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.imprint'],
            array_column($cold->getStaticRoutes(), 'routeName')
        );
        static::assertSame(0, $queries);
    }

    public function testResetOnlyDropsTheInProcessMemoisation(): void
    {
        $queries = 0;
        $loader = $this->createLoader([$this->staticRouteRow('imprint', ['en-GB' => 'imprint'])], $queries);

        $loader->getStaticRoutes();
        $loader->reset();

        static::assertCount(1, $loader->getStaticRoutes());
        static::assertSame(1, $queries);
        static::assertTrue($this->cache->getItem(AppSeoUrlRouteLoader::CACHE_KEY)->isHit());
    }

    public function testInvalidateCacheForcesTheNextLookupBackToTheDatabase(): void
    {
        $queries = 0;
        $loader = $this->createLoader([$this->staticRouteRow('imprint', ['en-GB' => 'imprint'])], $queries);

        $loader->getStaticRoutes();
        $loader->invalidateCache();

        static::assertFalse($this->cache->getItem(AppSeoUrlRouteLoader::CACHE_KEY)->isHit());
        static::assertCount(1, $loader->getStaticRoutes());
        static::assertSame(2, $queries);
    }

    public function testWrittenAppsInvalidateTheCache(): void
    {
        static::assertSame(
            [
                'app.written' => 'invalidateCache',
                'app_seo_url_route.written' => 'invalidateCache',
            ],
            AppSeoUrlRouteLoader::getSubscribedEvents()
        );
    }

    public function testNoRoutesAreReturnedWhileTheTableDoesNotExistYet(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(
            new TableNotFoundException(new DbalPdoException('Table not found', null, 1146), null)
        );

        $loader = new AppSeoUrlRouteLoader($connection, $this->definitionRegistry, $this->cache);

        static::assertSame([], $loader->getStaticRoutes());
        static::assertSame([], $loader->getEntityRoutes());
        static::assertSame([], iterator_to_array($loader->load(), false));
        static::assertFalse($this->cache->getItem(AppSeoUrlRouteLoader::CACHE_KEY)->isHit());
    }

    /**
     * @param list<array<string, string|null>> $rows
     */
    private function createLoader(array $rows, ?int &$queries = null): AppSeoUrlRouteLoader
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function () use ($rows, &$queries): array {
                if ($queries !== null) {
                    ++$queries;
                }

                return $rows;
            }
        );

        return new AppSeoUrlRouteLoader($connection, $this->definitionRegistry, $this->cache);
    }

    /**
     * @return array<string, string|null>
     */
    private function entityRouteRow(string $name, string $entityName): array
    {
        return [
            'appId' => self::APP_ID,
            'routeName' => 'storefront.app.SwagSeoUrlApp.' . $name,
            'hook' => $name,
            'entityName' => $entityName,
            'defaultTemplate' => '{{ ' . $entityName . '.translated.name }}',
            'paths' => null,
        ];
    }

    /**
     * @param array<string, string>|null $paths
     *
     * @return array<string, string|null>
     */
    private function staticRouteRow(string $name, ?array $paths): array
    {
        return [
            'appId' => self::APP_ID,
            'routeName' => 'storefront.app.SwagSeoUrlApp.' . $name,
            'hook' => $name,
            'entityName' => null,
            'defaultTemplate' => null,
            'paths' => $paths === null ? null : json_encode($paths, \JSON_THROW_ON_ERROR),
        ];
    }
}
