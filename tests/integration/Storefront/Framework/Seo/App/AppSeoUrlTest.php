<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlIndexer;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use BasicTestDataBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const APP_NAME = 'SwagStorefrontSeoUrl';

    private const IMPRINT_ROUTE = 'storefront.app.SwagStorefrontSeoUrl.imprint';

    private const PRODUCT_ROUTE = 'storefront.app.SwagStorefrontSeoUrl.app-product';

    private const PRODUCT_REVIEWS_ROUTE = 'storefront.app.SwagStorefrontSeoUrl.product-reviews';

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

    public function testInstallingTheAppStoresOneAppFeaturePerDeclaredSeoUrl(): void
    {
        $this->installApp();

        static::assertSame([
            ['type' => 'storefront_entity_seo_url', 'name' => 'app-product'],
            ['type' => 'storefront_seo_url', 'name' => 'imprint'],
        ], $this->connection->fetchAllAssociative(
            'SELECT `type`, `name` FROM `app_feature` WHERE `app_id` = :appId ORDER BY `type`, `name`',
            ['appId' => Uuid::fromHexToBytes($this->loadApp()->getId())]
        ));
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

    public function testWritingAnEntityWithIndexingDisabledDoesNotGenerateItsSeoUrl(): void
    {
        $this->installApp();

        $this->context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $this->createProduct();
        $this->runWorker();

        static::assertSame([], $this->fetchSeoUrls(self::PRODUCT_ROUTE, $this->getSalesChannelId()));
    }

    public function testWritingAnEntityWithQueuedIndexingGeneratesItsSeoUrlOnlyInTheWorker(): void
    {
        $this->installApp();

        $this->context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);
        $this->createProduct();

        static::assertSame([], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));

        $this->runWorker();

        static::assertSame(['app-product/app-product-1'], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));
    }

    public function testAFullIndexRunOfTheAppSeoUrlIndexerRegeneratesRemovedSeoUrls(): void
    {
        $this->installApp();
        $this->createProduct();
        $this->connection->executeStatement(
            'DELETE FROM `seo_url` WHERE `route_name` = :routeName',
            ['routeName' => self::PRODUCT_ROUTE]
        );
        $this->connection->executeStatement(
            'UPDATE `seo_url` SET `is_deleted` = 1 WHERE `route_name` = :routeName',
            ['routeName' => self::IMPRINT_ROUTE]
        );

        static::assertSame([], $this->fetchCanonicalSeoPaths(self::IMPRINT_ROUTE));

        static::getContainer()->get(EntityIndexerRegistry::class)->index(useQueue: false, only: [AppSeoUrlIndexer::NAME]);
        $this->runWorker();

        static::assertSame(['app-product/app-product-1'], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));
        static::assertSame(['imprint'], $this->fetchCanonicalSeoPaths(self::IMPRINT_ROUTE));
    }

    public function testChangingTheTemplateOfTheRouteRegeneratesTheSeoUrlWithTheNewTemplate(): void
    {
        $this->installApp();
        $this->createProduct();

        $this->changeDefaultTemplate(self::PRODUCT_ROUTE, 'products/{{ product.productNumber }}');
        $this->runWorker();

        static::assertSame(['products/app-product-1'], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));
    }

    public function testEditingTheCanonicalUrlOfTheAppRouteKeepsTheAppRoute(): void
    {
        $salesChannelId = $this->getSalesChannelId();
        $this->installApp();
        $productId = $this->createProduct(visibleIn: $salesChannelId)->get('app-product-1');
        $this->runWorker();

        $detailPageSeoUrls = $this->fetchSeoUrls(ProductPageSeoUrlRoute::ROUTE_NAME, $salesChannelId);
        static::assertContains($productId, array_column($detailPageSeoUrls, 'foreignKey'));

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', [
            'routeName' => self::PRODUCT_ROUTE,
            'foreignKey' => $productId,
            'salesChannelId' => $salesChannelId,
            'pathInfo' => '/storefront/script/app-product?id=' . $productId,
            'seoPathInfo' => 'my-app-product',
            'isCanonical' => true,
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(['my-app-product'], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));
        static::assertSame($detailPageSeoUrls, $this->fetchSeoUrls(ProductPageSeoUrlRoute::ROUTE_NAME, $salesChannelId));
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

    public function testANewSalesChannelDomainGetsTheStaticPathOfItsLocaleOnTheNextFullIndexRun(): void
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
        $this->runWorker();

        static::assertSame(
            [Defaults::LANGUAGE_SYSTEM => 'imprint'],
            $this->fetchSeoPathsByLanguage(self::IMPRINT_ROUTE, $salesChannelId)
        );

        static::getContainer()->get(EntityIndexerRegistry::class)->index(useQueue: false, only: [AppSeoUrlIndexer::NAME]);
        $this->runWorker();

        static::assertSame(
            [$germanId => 'impressum', Defaults::LANGUAGE_SYSTEM => 'imprint'],
            $this->fetchSeoPathsByLanguage(self::IMPRINT_ROUTE, $salesChannelId)
        );
    }

    public function testInstallingAnotherAppThatDeclaresTheSameStaticPathFails(): void
    {
        $this->installApp();

        $this->expectExceptionObject(SeoException::appSeoUrlPathAlreadyRegistered('legal-notice', 'imprint', self::APP_NAME));

        $this->installAnotherApp('SwagLegalNotice');
    }

    public function testInstallingAnAppWhoseStaticPathIsTheCanonicalSeoUrlOfAnotherRouteFails(): void
    {
        /** @var EntityRepository<LandingPageCollection> $repository */
        $repository = static::getContainer()->get('landing_page.repository');
        $repository->create([[
            'name' => 'Imprint',
            'url' => 'imprint',
            'salesChannels' => [['id' => $this->getSalesChannelId()]],
        ]], $this->context);

        static::assertSame(['imprint'], $this->fetchCanonicalSeoPaths('frontend.landing.page'));

        $this->expectExceptionObject(SeoException::appSeoUrlPathInUse('legal-notice', 'imprint'));

        $this->installAnotherApp('SwagLegalNotice');
    }

    public function testInstallingAnotherAppThatDeclaresTheSameHookFails(): void
    {
        $this->installApp();

        $this->expectExceptionObject(SeoException::appSeoUrlHookAlreadyRegistered('other', 'app-product', self::APP_NAME));

        $this->installAnotherApp('SwagHookThief');
    }

    public function testInstallingAnotherAppWithAHookOfItsOwnSucceeds(): void
    {
        $this->installApp();

        $this->installAnotherApp('own-hook/SwagHookThief');

        static::assertSame(
            ['product', 'other/{{ product.productNumber }}'],
            $this->fetchDefaultTemplate('storefront.app.SwagHookThief.other')
        );
    }

    public function testInstallingAnAppThatMayNotReadTheEntityOfItsEntitySeoUrlFails(): void
    {
        $this->expectExceptionObject(SeoException::appEntitySeoUrlNotPermitted('customer', 'customer', ['customer:read']));

        $this->installAnotherApp('SwagCustomerLeak');
    }

    public function testInstallingAnAppWhoseDefaultTemplateReadsAnAssociationItMayNotReadFails(): void
    {
        $this->expectExceptionObject(SeoException::appEntitySeoUrlNotPermitted('manufacturer-product', 'product', ['product_manufacturer:read']));

        $this->installAnotherApp('SwagManufacturerLeak');
    }

    public function testUpdatingTheAppRemovesTheSeoUrlsAndTheTemplateOfARouteItNoLongerDeclares(): void
    {
        $this->installApp('previous-version/SwagStorefrontSeoUrl');
        $this->createProduct();

        $this->updateApp('SwagStorefrontSeoUrl');
        $this->runWorker();

        static::assertSame([1], array_values(array_unique($this->fetchDeletedFlags(self::PRODUCT_REVIEWS_ROUTE))));
        static::assertNull($this->fetchDefaultTemplate(self::PRODUCT_REVIEWS_ROUTE));

        static::assertSame(['app-product/app-product-1'], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::PRODUCT_ROUTE));
        static::assertSame(['imprint'], $this->fetchCanonicalSeoPaths(self::IMPRINT_ROUTE));
    }

    public function testUpdatingAnEntitySeoUrlToAnotherEntityReplacesItsSeoUrlsAndResetsTheTemplate(): void
    {
        $this->installApp('previous-version/SwagStorefrontSeoUrl');
        $ids = $this->createProduct();
        static::getContainer()->get('product_review.repository')->create([[
            'productId' => $ids->get('app-product-1'),
            'salesChannelId' => $this->getSalesChannelId(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'title' => 'excellent',
            'content' => 'Does what it says on the box',
        ]], $this->context);
        $this->createSalesChannelTemplate(self::PRODUCT_REVIEWS_ROUTE, 'product', 'my-reviews/{{ product.productNumber }}');
        $this->runWorker();

        $this->updateApp('next-version/SwagStorefrontSeoUrl');

        static::assertSame([1], array_values(array_unique($this->fetchDeletedFlags(self::PRODUCT_REVIEWS_ROUTE))));
        static::assertSame(
            [['salesChannelId' => null, 'entityName' => 'product_review', 'template' => 'product-reviews/{{ productReview.title }}']],
            $this->fetchTemplates(self::PRODUCT_REVIEWS_ROUTE)
        );

        $this->runWorker();

        static::assertSame(['product-reviews/excellent'], $this->fetchCanonicalSeoPaths(self::PRODUCT_REVIEWS_ROUTE));
    }

    public function testChangingTheHookRetargetsTheSeoUrlsAndKeepsTheirPaths(): void
    {
        $salesChannelId = $this->getSalesChannelId();
        $this->installApp();
        $productId = $this->createProduct()->get('app-product-1');
        $this->runWorker();

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', [
            'routeName' => self::PRODUCT_ROUTE,
            'foreignKey' => $productId,
            'salesChannelId' => $salesChannelId,
            'pathInfo' => '/storefront/script/app-product?id=' . $productId,
            'seoPathInfo' => 'my-app-product',
            'isCanonical' => true,
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->updateApp('hook-change/SwagStorefrontSeoUrl');
        $this->runWorker();

        static::assertSame([[
            'foreignKey' => Uuid::fromStringToHex(self::IMPRINT_ROUTE),
            'pathInfo' => '/storefront/script/company-imprint',
            'seoPathInfo' => 'imprint',
            'isCanonical' => 1,
            'isModified' => 1,
            'isDeleted' => 0,
        ]], array_map($this->withoutIds(...), $this->fetchSeoUrls(self::IMPRINT_ROUTE, $salesChannelId)));

        $productPathInfo = '/storefront/script/product-page?id=' . $productId;
        static::assertSame([
            [
                'foreignKey' => $productId,
                'pathInfo' => $productPathInfo,
                'seoPathInfo' => 'app-product/app-product-1',
                'isCanonical' => null,
                'isModified' => 0,
                'isDeleted' => 0,
            ],
            [
                'foreignKey' => $productId,
                'pathInfo' => $productPathInfo,
                'seoPathInfo' => 'my-app-product',
                'isCanonical' => 1,
                'isModified' => 1,
                'isDeleted' => 0,
            ],
        ], array_map($this->withoutIds(...), $this->fetchSeoUrls(self::PRODUCT_ROUTE, $salesChannelId)));

        $response = $this->request('GET', 'my-app-product', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('product-page', $body['page'] ?? null);
        static::assertSame($productId, $body['productId'] ?? null);
    }

    public function testDeactivatingTheAppMarksTheSeoUrlsAsDeleted(): void
    {
        $this->installApp();
        $this->createProduct();

        static::getContainer()->get(AppManager::class)->deactivate($this->loadApp(), $this->context);

        $imprintFlags = $this->fetchDeletedFlags(self::IMPRINT_ROUTE);
        $productFlags = $this->fetchDeletedFlags(self::PRODUCT_ROUTE);

        static::assertNotEmpty($imprintFlags);
        static::assertNotEmpty($productFlags);
        static::assertSame([1], array_values(array_unique($imprintFlags)));
        static::assertSame([1], array_values(array_unique($productFlags)));
        static::assertNotNull($this->fetchDefaultTemplate(self::PRODUCT_ROUTE));
    }

    public function testUninstallingTheAppMarksTheSeoUrlsAsDeletedAndRemovesTheTemplate(): void
    {
        $this->installApp();
        $this->createProduct();

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context);

        $imprintFlags = $this->fetchDeletedFlags(self::IMPRINT_ROUTE);
        $productFlags = $this->fetchDeletedFlags(self::PRODUCT_ROUTE);

        static::assertNotEmpty($imprintFlags);
        static::assertNotEmpty($productFlags);
        static::assertSame([1], array_values(array_unique($imprintFlags)));
        static::assertSame([1], array_values(array_unique($productFlags)));
        static::assertNull($this->fetchDefaultTemplate(self::PRODUCT_ROUTE));
    }

    public function testUninstallingTheAppWithKeepUserDataKeepsTheEditedTemplateForTheReinstallation(): void
    {
        $this->installApp();
        $this->createProduct();
        $this->changeDefaultTemplate(self::PRODUCT_ROUTE, 'merchant/{{ product.productNumber }}');
        $this->runWorker();

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context, keepUserData: true);

        static::assertSame(['product', 'merchant/{{ product.productNumber }}'], $this->fetchDefaultTemplate(self::PRODUCT_ROUTE));

        $this->installApp();

        static::assertSame(['product', 'merchant/{{ product.productNumber }}'], $this->fetchDefaultTemplate(self::PRODUCT_ROUTE));
        static::assertSame(['merchant/app-product-1'], $this->fetchCanonicalSeoPaths(self::PRODUCT_ROUTE));
    }

    public function testReinstallingWithKeptUserDataResetsEveryTemplateOfARouteTheNewVersionBindsToAnotherEntity(): void
    {
        $this->installApp('previous-version/SwagStorefrontSeoUrl');
        $this->createSalesChannelTemplate(self::PRODUCT_REVIEWS_ROUTE, 'product', 'my-reviews/{{ product.productNumber }}');

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context, keepUserData: true);

        static::assertCount(2, $this->fetchTemplates(self::PRODUCT_REVIEWS_ROUTE));

        $this->installApp('next-version/SwagStorefrontSeoUrl');

        static::assertSame(
            [['salesChannelId' => null, 'entityName' => 'product_review', 'template' => 'product-reviews/{{ productReview.title }}']],
            $this->fetchTemplates(self::PRODUCT_REVIEWS_ROUTE)
        );
    }

    private function installApp(string $fixture = 'SwagStorefrontSeoUrl'): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/' . $fixture);

        $this->runWorker();
    }

    private function updateApp(string $fixture): void
    {
        static::getContainer()->get(AppManager::class)->update(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/' . $fixture . '/manifest.xml'),
            new AppUpdateParameters(),
            $this->loadApp(),
            $this->context
        );
    }

    private function installAnotherApp(string $fixture): void
    {
        static::getContainer()->get(AppManager::class)->install(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/' . $fixture . '/manifest.xml'),
            new AppInstallParameters(),
            $this->context
        );
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

    private function createProduct(?string $visibleIn = null): IdsCollection
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'app-product-1'))
            ->price(100)
            ->manufacturer('m1');

        if ($visibleIn !== null) {
            $product->visibility($visibleIn);
        }

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $repository->create([$product->build()], $this->context);

        return $ids;
    }

    private function changeDefaultTemplate(string $routeName, string $template): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));
        $criteria->addFilter(new EqualsFilter('salesChannelId', null));

        /** @var EntityRepository<SeoUrlTemplateCollection> $repository */
        $repository = static::getContainer()->get('seo_url_template.repository');
        $templateId = $repository->searchIds($criteria, $this->context)->firstId();
        static::assertNotNull($templateId);

        $repository->update([['id' => $templateId, 'template' => $template]], $this->context);
    }

    private function createSalesChannelTemplate(string $routeName, string $entityName, string $template): void
    {
        /** @var EntityRepository<SeoUrlTemplateCollection> $repository */
        $repository = static::getContainer()->get('seo_url_template.repository');
        $repository->create([[
            'salesChannelId' => $this->getSalesChannelId(),
            'routeName' => $routeName,
            'entityName' => $entityName,
            'template' => $template,
            'isValid' => true,
        ]], $this->context);
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
     * @return array<string, string> language id => SEO path
     */
    private function fetchSeoPathsByLanguage(string $routeName, string $salesChannelId): array
    {
        $paths = [];

        foreach ($this->fetchSeoUrls($routeName, $salesChannelId) as $row) {
            $paths[(string) $row['languageId']] = (string) $row['seoPathInfo'];
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function fetchCanonicalSeoPaths(string $routeName): array
    {
        /** @var list<string> $paths */
        $paths = $this->connection->fetchFirstColumn(
            'SELECT `seo_path_info` FROM `seo_url`
             WHERE `route_name` = :routeName AND `is_canonical` = 1 AND `is_deleted` = 0
             ORDER BY `seo_path_info`',
            ['routeName' => $routeName]
        );

        return $paths;
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
     * @return list<array<string, mixed>>
     */
    private function fetchTemplates(string $routeName): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`sales_channel_id`)) AS `salesChannelId`, `entity_name` AS `entityName`, `template`
             FROM `seo_url_template`
             WHERE `route_name` = :routeName
             ORDER BY `sales_channel_id`',
            ['routeName' => $routeName]
        );

        return $rows;
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
