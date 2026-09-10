<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const APP_NAME = 'SwagStorefrontSeoUrl';

    private const IMPRINT_ROUTE = 'storefront.app.SwagStorefrontSeoUrl.imprint';

    private const PRODUCT_ROUTE = 'storefront.app.SwagStorefrontSeoUrl.app-product';

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testActivatingTheAppWritesTheStaticSeoUrlAndSeedsTheEntityTemplate(): void
    {
        $this->installApp();

        $rows = $this->fetchSeoUrls(self::IMPRINT_ROUTE, $this->getSalesChannelId());
        static::assertCount(1, $rows);
        static::assertSame([
            'foreignKey' => Uuid::fromStringToHex(self::IMPRINT_ROUTE),
            'pathInfo' => '/storefront/script/imprint',
            'seoPathInfo' => 'imprint',
            'isCanonical' => 1,
            'isModified' => 1,
            'isDeleted' => 0,
        ], $this->withoutIds($rows[0]));

        static::assertSame(
            ['product', 'app-product/{{ product.productNumber }}'],
            $this->fetchDefaultTemplate(self::PRODUCT_ROUTE)
        );
        static::assertNull($this->fetchDefaultTemplate(self::IMPRINT_ROUTE));
    }

    public function testTheEntityRouteIsOnlyKnownToTheRegistryWhileTheAppIsActive(): void
    {
        $this->installApp();

        $registry = static::getContainer()->get(SeoUrlRouteRegistry::class);

        $route = $registry->findByRouteName(self::PRODUCT_ROUTE);
        static::assertNotNull($route);
        static::assertSame('frontend.script_endpoint', $route->getConfig()->getTargetRouteName());
        static::assertSame('product', $route->getConfig()->getDefinition()->getEntityName());

        static::getContainer()->get(AppManager::class)->deactivate($this->loadApp(), $this->context);

        static::assertNull($registry->findByRouteName(self::PRODUCT_ROUTE));
    }

    public function testWritingAnEntityGeneratesItsCanonicalSeoUrl(): void
    {
        $this->installApp();
        $ids = $this->createProduct();

        $rows = $this->fetchSeoUrls(self::PRODUCT_ROUTE, $this->getSalesChannelId());
        static::assertCount(1, $rows);
        static::assertSame([
            'foreignKey' => $ids->get('app-product-1'),
            'pathInfo' => '/storefront/script/app-product?id=' . $ids->get('app-product-1'),
            'seoPathInfo' => 'app-product/app-product-1',
            'isCanonical' => 1,
            'isModified' => 0,
            'isDeleted' => 0,
        ], $this->withoutIds($rows[0]));
    }

    public function testTheStorefrontServesTheGeneratedSeoUrlWithTheEntityIdInTheQuery(): void
    {
        $this->installApp();
        $ids = $this->createProduct();

        $response = $this->request('GET', 'app-product/app-product-1', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('app-product', $body['page'] ?? null);
        static::assertSame($ids->get('app-product-1'), $body['productId'] ?? null);
    }

    public function testTheStorefrontServesTheStaticSeoUrl(): void
    {
        $this->installApp();

        $response = $this->request('GET', 'imprint', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('imprint', $body['page'] ?? null);
    }

    public function testANewSalesChannelDomainGetsTheStaticPathOfItsLocale(): void
    {
        $this->installApp();

        $salesChannelId = $this->getSalesChannelId();
        $germanId = $this->getDeDeLanguageId();

        static::getContainer()->get('sales_channel_domain.repository')->create([[
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'languageId' => $germanId,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            'url' => 'http://localhost/swag-seo-url-app-de',
        ]], $this->context);

        $rows = $this->fetchSeoUrls(self::IMPRINT_ROUTE, $salesChannelId);
        static::assertCount(2, $rows);

        $paths = [];
        foreach ($rows as $row) {
            $paths[(string) $row['languageId']] = $row['seoPathInfo'];
        }

        static::assertSame('imprint', $paths[Defaults::LANGUAGE_SYSTEM] ?? null);
        static::assertSame('impressum', $paths[$germanId] ?? null);
    }

    public function testDeactivatingTheAppMarksTheSeoUrlsAsDeleted(): void
    {
        $this->installApp();
        $this->createProduct();

        static::getContainer()->get(AppManager::class)->deactivate($this->loadApp(), $this->context);

        static::assertSame([1], $this->fetchDeletedFlags(self::IMPRINT_ROUTE));
        static::assertSame([1], $this->fetchDeletedFlags(self::PRODUCT_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::PRODUCT_ROUTE));
    }

    public function testUninstallingTheAppRemovesTheSeoUrlsAndTheTemplate(): void
    {
        $this->installApp();
        $this->createProduct();

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context);

        static::assertSame([], $this->fetchDeletedFlags(self::IMPRINT_ROUTE));
        static::assertSame([], $this->fetchDeletedFlags(self::PRODUCT_ROUTE));
        static::assertNull($this->fetchDefaultTemplate(self::PRODUCT_ROUTE));
    }

    private function installApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');
    }

    private function loadApp(): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::APP_NAME));

        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');
        $app = $repository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
    }

    private function createProduct(): IdsCollection
    {
        $ids = new IdsCollection();

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $repository->create([
            (new ProductBuilder($ids, 'app-product-1'))
                ->price(100)
                ->manufacturer('m1')
                ->build(),
        ], $this->context);

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSeoUrls(string $routeName, string $salesChannelId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`language_id`)) AS `languageId`,
                    LOWER(HEX(`foreign_key`)) AS `foreignKey`,
                    `path_info` AS `pathInfo`,
                    `seo_path_info` AS `seoPathInfo`,
                    `is_canonical` AS `isCanonical`,
                    `is_modified` AS `isModified`,
                    `is_deleted` AS `isDeleted`
             FROM `seo_url`
             WHERE `route_name` = :routeName AND `sales_channel_id` = :salesChannelId
             ORDER BY `seo_path_info`',
            ['routeName' => $routeName, 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withoutIds(array $row): array
    {
        unset($row['languageId']);

        return array_map(
            static fn (mixed $value): mixed => \is_numeric($value) ? (int) $value : $value,
            $row
        );
    }

    /**
     * @return list<int>
     */
    private function fetchDeletedFlags(string $routeName): array
    {
        return array_map(
            static fn (mixed $isDeleted): int => (int) $isDeleted,
            $this->connection->fetchFirstColumn(
                'SELECT `is_deleted` FROM `seo_url` WHERE `route_name` = :routeName',
                ['routeName' => $routeName]
            )
        );
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function fetchDefaultTemplate(string $routeName): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `entity_name`, `template` FROM `seo_url_template`
             WHERE `route_name` = :routeName AND `sales_channel_id` IS NULL',
            ['routeName' => $routeName]
        );

        if ($row === false) {
            return null;
        }

        return [(string) $row['entity_name'], (string) $row['template']];
    }
}
