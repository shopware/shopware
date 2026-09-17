<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\SeoUrlRouteLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SeoUrlRouteLifecycleHandler::class)]
class SeoUrlRouteLifecycleHandlerTest extends TestCase
{
    private const APP_NAME = 'SwagSeoUrlApp';

    private const IMPRINT_ROUTE = 'storefront.app.SwagSeoUrlApp.imprint';

    private const TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const LEGACY_ROUTE = 'storefront.app.SwagSeoUrlApp.legacy';

    private const TEASER_TEMPLATE = '{{ product.translated.name }}';

    private AppEntity $app;

    protected function setUp(): void
    {
        $this->app = AppFixture::createAppEntity(self::APP_NAME);
    }

    public function testInstallUpsertsEveryDeclaredRouteWithItsComputedRouteName(): void
    {
        $routeRepository = $this->routeRepository();

        $this->handler($routeRepository, $this->seoUrlRepository(), $this->templateRepository(new SeoUrlTemplateCollection()))
            ->install($this->persistContext($this->manifest()));

        static::assertSame([[
            [
                'name' => 'imprint',
                'hook' => 'imprint',
                'label' => ['en-GB' => 'Imprint'],
                'defaultTemplate' => null,
                'entityName' => null,
                'paths' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
                'appId' => $this->app->getId(),
                'routeName' => self::IMPRINT_ROUTE,
            ],
            [
                'name' => 'product-teaser',
                'hook' => 'product-teaser',
                'label' => ['en-GB' => 'Product teaser'],
                'defaultTemplate' => self::TEASER_TEMPLATE,
                'entityName' => 'product',
                'paths' => null,
                'appId' => $this->app->getId(),
                'routeName' => self::TEASER_ROUTE,
            ],
        ]], $routeRepository->upserts);

        static::assertSame([], $routeRepository->deletes);
    }

    public function testInstallCreatesTheDefaultTemplateOfEntityBoundRoutesOnly(): void
    {
        $templateRepository = $this->templateRepository(new SeoUrlTemplateCollection());

        $this->handler($this->routeRepository(), $this->seoUrlRepository(), $templateRepository)
            ->install($this->persistContext($this->manifest()));

        static::assertCount(1, $templateRepository->creates);

        $payloads = $templateRepository->getPayloads(StaticEntityRepository::CREATE);
        static::assertCount(1, $payloads);

        $payload = $payloads[0];
        static::assertArrayHasKey('id', $payload);
        unset($payload['id']);

        static::assertSame([
            'salesChannelId' => null,
            'routeName' => self::TEASER_ROUTE,
            'entityName' => 'product',
            'template' => self::TEASER_TEMPLATE,
            'isValid' => true,
            'isHeadless' => false,
        ], $payload);
    }

    public function testUpdateKeepsTheIdsOfTheAlreadyPersistedRoutes(): void
    {
        $imprint = $this->route('imprint', self::IMPRINT_ROUTE);
        $teaser = $this->route('product-teaser', self::TEASER_ROUTE, 'product', self::TEASER_TEMPLATE);

        $routeRepository = $this->routeRepository($imprint, $teaser);

        $this->handler(
            $routeRepository,
            $this->seoUrlRepository(),
            $this->templateRepository(new SeoUrlTemplateCollection([$this->template('product', self::TEASER_TEMPLATE)]))
        )->update($this->persistContext($this->manifest()));

        static::assertSame(
            [$imprint->id, $teaser->id],
            array_column($routeRepository->getPayloads(StaticEntityRepository::UPSERT), 'id')
        );

        static::assertSame([], $routeRepository->deletes);
    }

    public function testUpdateRemovesRoutesThatAreNoLongerDeclaredAndTheirGeneratedUrls(): void
    {
        $legacy = $this->route('legacy', self::LEGACY_ROUTE);

        $routeRepository = $this->routeRepository($legacy);
        $seoUrlRepository = $this->seoUrlRepository(['seo-url-1', 'seo-url-2']);
        $templateRepository = $this->templateRepository(['seo-url-template-1'], new SeoUrlTemplateCollection());

        $this->handler($routeRepository, $seoUrlRepository, $templateRepository)
            ->update($this->persistContext($this->manifest()));

        static::assertSame([[['id' => $legacy->id]]], $routeRepository->deletes);

        static::assertSame([[
            ['id' => 'seo-url-1', 'isDeleted' => true],
            ['id' => 'seo-url-2', 'isDeleted' => true],
        ]], $seoUrlRepository->updates);

        static::assertSame([[['id' => 'seo-url-template-1']]], $templateRepository->deletes);
    }

    public function testOnlyTheUrlsOfTheRemovedRouteAreTouched(): void
    {
        $criteria = null;

        $seoUrlRepository = StaticEntityRepository::of(SeoUrlCollection::class, [
            static function (Criteria $given) use (&$criteria): array {
                $criteria = $given;

                return [];
            },
        ]);

        $this->handler(
            $this->routeRepository($this->route('legacy', self::LEGACY_ROUTE)),
            $seoUrlRepository,
            $this->templateRepository([], new SeoUrlTemplateCollection())
        )->update($this->persistContext($this->manifest()));

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertEquals(
            [
                new EqualsAnyFilter('routeName', [self::LEGACY_ROUTE]),
                new EqualsFilter('isDeleted', false),
            ],
            $criteria->getFilters()
        );
    }

    public function testUpdateOverwritesADefaultTemplateThatStillEqualsThePreviousDefault(): void
    {
        $existing = $this->template('product', 'teaser/{{ product.id }}');

        $templateRepository = $this->templateRepository(new SeoUrlTemplateCollection([$existing]));

        $this->handler(
            $this->routeRepository($this->route('product-teaser', self::TEASER_ROUTE, 'product', 'teaser/{{ product.id }}')),
            $this->seoUrlRepository(),
            $templateRepository
        )->update($this->persistContext($this->manifest()));

        static::assertSame(
            [[['id' => $existing->getId(), 'template' => self::TEASER_TEMPLATE]]],
            $templateRepository->updates
        );
    }

    public function testUpdateKeepsADefaultTemplateThatWasChangedByTheMerchant(): void
    {
        $templateRepository = $this->templateRepository(
            new SeoUrlTemplateCollection([$this->template('product', 'my-teaser/{{ product.id }}')])
        );

        $this->handler(
            $this->routeRepository($this->route('product-teaser', self::TEASER_ROUTE, 'product', 'teaser/{{ product.id }}')),
            $this->seoUrlRepository(),
            $templateRepository
        )->update($this->persistContext($this->manifest()));

        static::assertSame([], $templateRepository->updates);
        static::assertSame([], $templateRepository->creates);
    }

    public function testUpdateResyncsTheEntityNameOfTheDefaultTemplate(): void
    {
        $existing = $this->template('category', self::TEASER_TEMPLATE);

        $templateRepository = $this->templateRepository(new SeoUrlTemplateCollection([$existing]));

        $this->handler(
            $this->routeRepository($this->route('product-teaser', self::TEASER_ROUTE, 'product', self::TEASER_TEMPLATE)),
            $this->seoUrlRepository(),
            $templateRepository
        )->update($this->persistContext($this->manifest()));

        static::assertSame(
            [[['id' => $existing->getId(), 'entityName' => 'product']]],
            $templateRepository->updates
        );
    }

    public function testDeactivateMarksTheGeneratedSeoUrlsAsDeletedAndKeepsTheTemplates(): void
    {
        $seoUrlRepository = $this->seoUrlRepository(['seo-url-1']);
        $templateRepository = $this->templateRepository();

        $this->handler(
            $this->routeRepository(
                $this->route('imprint', self::IMPRINT_ROUTE),
                $this->route('product-teaser', self::TEASER_ROUTE, 'product', self::TEASER_TEMPLATE)
            ),
            $seoUrlRepository,
            $templateRepository
        )->deactivate(new AppActivationContext($this->app, Context::createDefaultContext()));

        static::assertSame([[['id' => 'seo-url-1', 'isDeleted' => true]]], $seoUrlRepository->updates);
        static::assertSame([], $templateRepository->deletes);
    }

    public function testUninstallMarksTheSeoUrlsAsDeletedAndRemovesTheTemplatesEvenWhenUserDataIsKept(): void
    {
        $seoUrlRepository = $this->seoUrlRepository(['seo-url-1']);
        $templateRepository = $this->templateRepository(['seo-url-template-1']);

        $this->handler($this->routeRepository($this->route('imprint', self::IMPRINT_ROUTE)), $seoUrlRepository, $templateRepository)
            ->uninstall(new AppRemovalContext($this->app, Context::createDefaultContext(), keepUserData: true));

        static::assertSame([[['id' => 'seo-url-1', 'isDeleted' => true]]], $seoUrlRepository->updates);
        static::assertSame([[['id' => 'seo-url-template-1']]], $templateRepository->deletes);
    }

    public function testDeleteMarksTheSeoUrlsAsDeletedAndRemovesTheTemplates(): void
    {
        $seoUrlRepository = $this->seoUrlRepository(['seo-url-1']);
        $templateRepository = $this->templateRepository(['seo-url-template-1']);

        $this->handler($this->routeRepository($this->route('imprint', self::IMPRINT_ROUTE)), $seoUrlRepository, $templateRepository)
            ->delete(new AppRemovalContext($this->app, Context::createDefaultContext()));

        static::assertSame([[['id' => 'seo-url-1', 'isDeleted' => true]]], $seoUrlRepository->updates);
        static::assertSame([[['id' => 'seo-url-template-1']]], $templateRepository->deletes);
    }

    public function testTheSeoUrlsAreMarkedAsDeletedInChunks(): void
    {
        $ids = [];
        for ($i = 0; $i < 600; ++$i) {
            $ids[] = Uuid::randomHex();
        }

        $seoUrlRepository = $this->seoUrlRepository($ids);

        $this->handler($this->routeRepository($this->route('imprint', self::IMPRINT_ROUTE)), $seoUrlRepository, $this->templateRepository())
            ->deactivate(new AppActivationContext($this->app, Context::createDefaultContext()));

        static::assertSame([500, 100], array_map('count', $seoUrlRepository->updates));
    }

    public function testNothingIsWrittenForAnAppWithoutSeoUrlRoutes(): void
    {
        $routeRepository = $this->routeRepository();
        $seoUrlRepository = $this->seoUrlRepository();
        $templateRepository = $this->templateRepository();

        $this->handler($routeRepository, $seoUrlRepository, $templateRepository)
            ->install($this->persistContext(ManifestFixture::empty()->withName(self::APP_NAME)));

        static::assertSame([], $routeRepository->upserts);
        static::assertSame([], $routeRepository->deletes);
        static::assertSame([], $seoUrlRepository->updates);
        static::assertSame([], $templateRepository->creates);
        static::assertSame([], $templateRepository->updates);
        static::assertSame([], $templateRepository->deletes);
    }

    /**
     * @param StaticEntityRepository<EntityCollection<AppSeoUrlRouteEntity>> $routeRepository
     * @param StaticEntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param StaticEntityRepository<SeoUrlTemplateCollection> $templateRepository
     */
    private function handler(
        StaticEntityRepository $routeRepository,
        StaticEntityRepository $seoUrlRepository,
        StaticEntityRepository $templateRepository
    ): SeoUrlRouteLifecycleHandler {
        return new SeoUrlRouteLifecycleHandler($routeRepository, $seoUrlRepository, $templateRepository);
    }

    /**
     * @return StaticEntityRepository<EntityCollection<AppSeoUrlRouteEntity>>
     */
    private function routeRepository(AppSeoUrlRouteEntity ...$existing): StaticEntityRepository
    {
        return new StaticEntityRepository([new EntityCollection($existing)]);
    }

    /**
     * @param list<string> $ids
     *
     * @return StaticEntityRepository<SeoUrlCollection>
     */
    private function seoUrlRepository(array $ids = []): StaticEntityRepository
    {
        return StaticEntityRepository::of(SeoUrlCollection::class, [$ids]);
    }

    /**
     * @param list<string>|SeoUrlTemplateCollection ...$searches
     *
     * @return StaticEntityRepository<SeoUrlTemplateCollection>
     */
    private function templateRepository(array|SeoUrlTemplateCollection ...$searches): StaticEntityRepository
    {
        return StaticEntityRepository::of(SeoUrlTemplateCollection::class, $searches);
    }

    private function persistContext(Manifest $manifest): AppPersistContext
    {
        return AppFixture::createInstallContext($this->app, $manifest);
    }

    private function manifest(string $template = self::TEASER_TEMPLATE): ManifestFixture
    {
        return ManifestFixture::empty()
            ->withName(self::APP_NAME)
            ->withSeoUrl(SeoUrl::fromArray([
                'name' => 'imprint',
                'label' => ['en-GB' => 'Imprint'],
                'path' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
            ]))
            ->withSeoUrl(SeoUrl::fromArray([
                'name' => 'product-teaser',
                'entity' => 'product',
                'label' => ['en-GB' => 'Product teaser'],
                'defaultTemplate' => $template,
            ]));
    }

    private function route(
        string $name,
        string $routeName,
        ?string $entityName = null,
        ?string $defaultTemplate = null
    ): AppSeoUrlRouteEntity {
        $route = new AppSeoUrlRouteEntity();
        $route->id = Uuid::randomHex();
        $route->appId = $this->app->getId();
        $route->name = $name;
        $route->routeName = $routeName;
        $route->hook = $name;
        $route->entityName = $entityName;
        $route->defaultTemplate = $defaultTemplate;
        $route->setUniqueIdentifier($route->id);

        return $route;
    }

    private function template(string $entityName, string $template): SeoUrlTemplateEntity
    {
        $entity = new SeoUrlTemplateEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setUniqueIdentifier($entity->getId());
        $entity->setRouteName(self::TEASER_ROUTE);
        $entity->setEntityName($entityName);
        $entity->setTemplate($template);

        return $entity;
    }
}
