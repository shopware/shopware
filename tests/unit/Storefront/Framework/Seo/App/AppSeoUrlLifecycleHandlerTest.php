<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlLifecycleHandler::class)]
class AppSeoUrlLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const APP_NAME = 'SwagSeoUrlApp';

    private const ROUTE_NAME_PREFIX = 'storefront.app.SwagSeoUrlApp.';

    private const IMPRINT_ROUTE = 'storefront.app.SwagSeoUrlApp.imprint';

    private const CONTACT_ROUTE = 'storefront.app.SwagSeoUrlApp.contact';

    private const PRODUCT_TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const CATEGORY_TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.category-teaser';

    private const BLOG_DETAIL_ROUTE = 'storefront.app.SwagSeoUrlApp.blog-detail';

    private const SEO_URL_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const OTHER_SEO_URL_ID = 'cccccccccccccccccccccccccccccccc';

    private const TEMPLATE_ID = 'dddddddddddddddddddddddddddddddd';

    private const OTHER_TEMPLATE_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private const SALES_CHANNEL_ID = 'ffffffffffffffffffffffffffffffff';

    /**
     * @var StaticEntityRepository<SeoUrlCollection>
     */
    private StaticEntityRepository $seoUrlRepository;

    /**
     * @var StaticEntityRepository<SeoUrlTemplateCollection>
     */
    private StaticEntityRepository $seoUrlTemplateRepository;

    private CollectingMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->seoUrlRepository = StaticEntityRepository::of(SeoUrlCollection::class);
        $this->seoUrlTemplateRepository = StaticEntityRepository::of(SeoUrlTemplateCollection::class);
        $this->messageBus = new CollectingMessageBus();
    }

    public function testInstallDeletesKeptTemplatesOfRoutesTheManifestDoesNotDeclareAsEntitySeoUrl(): void
    {
        $this->seoUrlTemplateRepository->addSearch(
            static function (Criteria $criteria): SeoUrlTemplateCollection {
                static::assertEquals([new PrefixFilter('routeName', self::ROUTE_NAME_PREFIX)], $criteria->getFilters());

                return new SeoUrlTemplateCollection([
                    self::template(self::PRODUCT_TEASER_ROUTE),
                    self::template(self::BLOG_DETAIL_ROUTE),
                    self::template(self::BLOG_DETAIL_ROUTE, salesChannelId: self::SALES_CHANNEL_ID),
                    self::template(self::IMPRINT_ROUTE),
                ]);
            },
            static function (Criteria $criteria): array {
                static::assertEquals([new EqualsAnyFilter('routeName', [self::BLOG_DETAIL_ROUTE, self::IMPRINT_ROUTE])], $criteria->getFilters());

                return [self::TEMPLATE_ID, self::OTHER_TEMPLATE_ID];
            },
        );

        $this->handler()->install(self::installContext(self::manifest(
            seoUrls: ['imprint'],
            entitySeoUrls: ['product-teaser' => 'product'],
        )));

        static::assertSame([[['id' => self::TEMPLATE_ID], ['id' => self::OTHER_TEMPLATE_ID]]], $this->seoUrlTemplateRepository->deletes);
        static::assertSame([], $this->seoUrlRepository->updates);
    }

    public function testInstallIgnoresTemplatesOfAnotherAppWhoseNameStartsWithTheAppName(): void
    {
        $this->seoUrlTemplateRepository->addSearch(new SeoUrlTemplateCollection([
            self::template('storefront.app.SwagSeoUrlApp.Legacy.product-teaser'),
        ]));

        $this->handler()->install(self::installContext(self::manifest()));

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @param list<string> $keptRouteNames
     */
    #[DataProvider('installsWithoutStaleTemplates')]
    public function testInstallWithoutStaleTemplatesDeletesNothing(array $keptRouteNames): void
    {
        $this->seoUrlTemplateRepository->addSearch(new SeoUrlTemplateCollection(array_map(self::template(...), $keptRouteNames)));

        $this->handler()->install(self::installContext(self::manifest(entitySeoUrls: ['product-teaser' => 'product'])));

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function installsWithoutStaleTemplates(): iterable
    {
        yield 'a first install without kept templates' => [[]];
        yield 'a reinstall whose kept templates are all declared again' => [[self::PRODUCT_TEASER_ROUTE]];
    }

    public function testUpdateMarksTheSeoUrlsOfRoutesTheManifestNoLongerDeclaresAsDeleted(): void
    {
        $this->seoUrlRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals(
                [
                    new EqualsAnyFilter('routeName', [self::CONTACT_ROUTE, self::BLOG_DETAIL_ROUTE]),
                    new EqualsFilter('isDeleted', false),
                ],
                $criteria->getFilters()
            );

            return [self::SEO_URL_ID, self::OTHER_SEO_URL_ID];
        });
        $this->seoUrlTemplateRepository->addSearch([]);

        $this->handler(
            storedSeoUrls: ['imprint', 'contact'],
            storedEntitySeoUrls: ['product-teaser' => 'product', 'blog-detail' => 'ce_blog'],
        )->update(self::updateContext(self::manifest(
            seoUrls: ['imprint'],
            entitySeoUrls: ['product-teaser' => 'product'],
        )));

        static::assertSame(
            [[['id' => self::SEO_URL_ID, 'isDeleted' => true], ['id' => self::OTHER_SEO_URL_ID, 'isDeleted' => true]]],
            $this->seoUrlRepository->updates
        );
    }

    /**
     * @param list<string> $declaredSeoUrls
     */
    #[DataProvider('entitySeoUrlsGoneFromTheManifest')]
    public function testUpdateDeletesTheTemplateOfAnEntitySeoUrlTheManifestNoLongerDeclares(array $declaredSeoUrls): void
    {
        $this->seoUrlRepository->addSearch([]);
        $this->seoUrlTemplateRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals([new EqualsAnyFilter('routeName', [self::BLOG_DETAIL_ROUTE])], $criteria->getFilters());

            return [self::TEMPLATE_ID];
        });

        $this->handler(storedEntitySeoUrls: ['product-teaser' => 'product', 'blog-detail' => 'ce_blog'])
            ->update(self::updateContext(self::manifest(
                seoUrls: $declaredSeoUrls,
                entitySeoUrls: ['product-teaser' => 'product'],
            )));

        static::assertSame([[['id' => self::TEMPLATE_ID]]], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function entitySeoUrlsGoneFromTheManifest(): iterable
    {
        yield 'an entity SEO URL that is not declared anymore' => [[]];
        yield 'an entity SEO URL that turned into a static SEO URL' => [['blog-detail']];
    }

    /**
     * @param list<string> $storedSeoUrls
     * @param array<string, string> $storedEntitySeoUrls
     * @param array<string, string> $declaredEntitySeoUrls
     */
    #[DataProvider('routesStillDeclaredAsEntitySeoUrl')]
    public function testUpdateMarksTheSeoUrlsOfARedeclaredRouteAsDeletedButKeepsItsTemplate(
        array $storedSeoUrls,
        array $storedEntitySeoUrls,
        array $declaredEntitySeoUrls,
        string $expectedRouteName,
    ): void {
        $this->seoUrlRepository->addSearch(static function (Criteria $criteria) use ($expectedRouteName): array {
            static::assertEquals(
                [
                    new EqualsAnyFilter('routeName', [$expectedRouteName]),
                    new EqualsFilter('isDeleted', false),
                ],
                $criteria->getFilters()
            );

            return [self::SEO_URL_ID];
        });

        $this->handler(storedSeoUrls: $storedSeoUrls, storedEntitySeoUrls: $storedEntitySeoUrls)
            ->update(self::updateContext(self::manifest(entitySeoUrls: $declaredEntitySeoUrls)));

        static::assertSame([[['id' => self::SEO_URL_ID, 'isDeleted' => true]]], $this->seoUrlRepository->updates);
        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{list<string>, array<string, string>, array<string, string>, string}>
     */
    public static function routesStillDeclaredAsEntitySeoUrl(): iterable
    {
        yield 'an entity SEO URL now bound to another entity' => [
            [],
            ['category-teaser' => 'category'],
            ['category-teaser' => 'product'],
            self::CATEGORY_TEASER_ROUTE,
        ];
        yield 'a static SEO URL that turned into an entity SEO URL' => [
            ['imprint'],
            [],
            ['imprint' => 'product'],
            self::IMPRINT_ROUTE,
        ];
    }

    public function testUpdateWithoutRemovedRoutesQueriesNothing(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
        )->update(self::updateContext(self::manifest(
            seoUrls: ['imprint', 'contact'],
            entitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
        )));

        static::assertSame([], $this->seoUrlRepository->updates);
        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    public function testUpdateLeavesTheRegenerationToTheAppUpdatedEvent(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
        )->update(self::updateContext(self::manifest(
            seoUrls: ['imprint'],
            entitySeoUrls: ['product-teaser' => 'product'],
        )));

        static::assertSame([], $this->dispatchedMessages());
    }

    public function testTheAppUpdatedEventTriggersTheRegeneration(): void
    {
        static::assertSame(
            [AppUpdatedEvent::class => 'regenerateUpdatedApp'],
            AppSeoUrlLifecycleHandler::getSubscribedEvents()
        );
    }

    public function testAnUpdatedActiveAppSynchronisesTheStaticSeoUrlsAndRebuildsEveryEntitySeoUrl(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
        )->regenerateUpdatedApp(self::appUpdatedEvent(active: true));

        static::assertEquals(
            [
                new AppSeoUrlSyncMessage(self::APP_ID),
                new SeoUrlTemplateIndexingMessage(self::PRODUCT_TEASER_ROUTE, 'product'),
                new SeoUrlTemplateIndexingMessage(self::CATEGORY_TEASER_ROUTE, 'category'),
            ],
            $this->dispatchedMessages()
        );
    }

    public function testAnUpdatedActiveAppWithoutEntitySeoUrlsOnlySynchronisesItsStaticSeoUrls(): void
    {
        $this->handler(storedSeoUrls: ['imprint'])->regenerateUpdatedApp(self::appUpdatedEvent(active: true));

        static::assertEquals([new AppSeoUrlSyncMessage(self::APP_ID)], $this->dispatchedMessages());
    }

    public function testAnUpdatedInactiveAppRegeneratesNothing(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
        )->regenerateUpdatedApp(self::appUpdatedEvent(active: false));

        static::assertSame([], $this->dispatchedMessages());
    }

    public function testActivationSynchronisesTheStaticSeoUrlsAndRebuildsEveryEntitySeoUrlOfTheApp(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
        )->activate(self::activationContext());

        static::assertEquals(
            [
                new AppSeoUrlSyncMessage(self::APP_ID),
                new SeoUrlTemplateIndexingMessage(self::PRODUCT_TEASER_ROUTE, 'product'),
                new SeoUrlTemplateIndexingMessage(self::CATEGORY_TEASER_ROUTE, 'category'),
            ],
            $this->dispatchedMessages()
        );
    }

    public function testDeactivationMarksTheSeoUrlsOfEveryStoredRouteAsDeletedButKeepsTheTemplates(): void
    {
        $this->seoUrlRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals(
                [
                    new EqualsAnyFilter('routeName', [self::IMPRINT_ROUTE, self::PRODUCT_TEASER_ROUTE]),
                    new EqualsFilter('isDeleted', false),
                ],
                $criteria->getFilters()
            );

            return [self::SEO_URL_ID];
        });

        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
        )->deactivate(self::activationContext());

        static::assertSame([[['id' => self::SEO_URL_ID, 'isDeleted' => true]]], $this->seoUrlRepository->updates);
        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
        static::assertSame([], $this->dispatchedMessages());
    }

    public function testDeactivatingAnAppWithoutSeoUrlsQueriesNothing(): void
    {
        $this->handler()->deactivate(self::activationContext());

        static::assertSame([], $this->seoUrlRepository->updates);
    }

    public function testSeoUrlsAreMarkedAsDeletedInChunksOf500(): void
    {
        $ids = array_map(static fn (): string => Uuid::randomHex(), range(1, 501));
        $this->seoUrlRepository->addSearch($ids);

        $this->handler(storedSeoUrls: ['imprint'])->deactivate(self::activationContext());

        static::assertCount(2, $this->seoUrlRepository->updates);
        static::assertCount(500, $this->seoUrlRepository->updates[0]);
        static::assertSame([['id' => $ids[500], 'isDeleted' => true]], $this->seoUrlRepository->updates[1]);
    }

    /**
     * @param \Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void $removal
     */
    #[DataProvider('removals')]
    public function testRemovingTheAppMarksItsSeoUrlsAsDeletedAndDeletesItsTemplates(\Closure $removal): void
    {
        $this->seoUrlRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals(
                [
                    new EqualsAnyFilter('routeName', [self::IMPRINT_ROUTE, self::PRODUCT_TEASER_ROUTE, self::CATEGORY_TEASER_ROUTE]),
                    new EqualsFilter('isDeleted', false),
                ],
                $criteria->getFilters()
            );

            return [self::SEO_URL_ID];
        });
        $this->seoUrlTemplateRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals(
                [new EqualsAnyFilter('routeName', [self::PRODUCT_TEASER_ROUTE, self::CATEGORY_TEASER_ROUTE])],
                $criteria->getFilters()
            );

            return [self::TEMPLATE_ID, self::OTHER_TEMPLATE_ID];
        });

        $removal(
            $this->handler(
                storedSeoUrls: ['imprint'],
                storedEntitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
            ),
            self::removalContext(keepUserData: false)
        );

        static::assertSame([[['id' => self::SEO_URL_ID, 'isDeleted' => true]]], $this->seoUrlRepository->updates);
        static::assertSame([[['id' => self::TEMPLATE_ID], ['id' => self::OTHER_TEMPLATE_ID]]], $this->seoUrlTemplateRepository->deletes);
        static::assertSame([], $this->dispatchedMessages());
    }

    /**
     * @param \Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void $removal
     */
    #[DataProvider('removals')]
    public function testRemovingTheAppWhileKeepingUserDataKeepsItsTemplates(\Closure $removal): void
    {
        $this->seoUrlRepository->addSearch([self::SEO_URL_ID]);

        $removal(
            $this->handler(storedEntitySeoUrls: ['product-teaser' => 'product']),
            self::removalContext(keepUserData: true)
        );

        static::assertSame([[['id' => self::SEO_URL_ID, 'isDeleted' => true]]], $this->seoUrlRepository->updates);
        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @param \Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void $removal
     */
    #[DataProvider('removals')]
    public function testRemovingAnAppWithoutSeoUrlsQueriesNothing(\Closure $removal): void
    {
        $removal($this->handler(), self::removalContext(keepUserData: false));

        static::assertSame([], $this->seoUrlRepository->updates);
        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{\Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void}>
     */
    public static function removals(): iterable
    {
        yield 'uninstalling the app' => [
            static fn (AppSeoUrlLifecycleHandler $handler, AppRemovalContext $context) => $handler->uninstall($context),
        ];
        yield 'deleting the app without notifying its app server' => [
            static fn (AppSeoUrlLifecycleHandler $handler, AppRemovalContext $context) => $handler->delete($context),
        ];
    }

    /**
     * @param list<string> $storedSeoUrls
     * @param array<string, string> $storedEntitySeoUrls
     */
    private function handler(array $storedSeoUrls = [], array $storedEntitySeoUrls = []): AppSeoUrlLifecycleHandler
    {
        $seoUrls = array_map(
            static fn (string $name): AppFeature => self::feature(new AppSeoUrlConfig(
                name: $name,
                routeName: self::ROUTE_NAME_PREFIX . $name,
                hook: $name,
                paths: ['en-GB' => $name],
            )),
            $storedSeoUrls
        );

        $entitySeoUrls = array_map(
            static fn (string $name, string $entityName): AppFeature => self::feature(new AppEntitySeoUrlConfig(
                name: $name,
                routeName: self::ROUTE_NAME_PREFIX . $name,
                hook: $name,
                entityName: $entityName,
                defaultTemplate: '{{ ' . $entityName . '.translated.name }}',
            )),
            array_keys($storedEntitySeoUrls),
            $storedEntitySeoUrls
        );

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forApp')->willReturnCallback(static function (string $appId, string $featureClass) use ($seoUrls, $entitySeoUrls): array {
            static::assertSame(self::APP_ID, $appId);

            return match ($featureClass) {
                AppSeoUrlConfig::class => $seoUrls,
                AppEntitySeoUrlConfig::class => $entitySeoUrls,
                default => static::fail('Unexpected feature class ' . $featureClass),
            };
        });

        return new AppSeoUrlLifecycleHandler(
            $storage,
            $this->seoUrlRepository,
            $this->seoUrlTemplateRepository,
            $this->messageBus,
        );
    }

    /**
     * @return list<object>
     */
    private function dispatchedMessages(): array
    {
        return array_values(array_map(
            static fn (Envelope $envelope): object => $envelope->getMessage(),
            $this->messageBus->getMessages()
        ));
    }

    /**
     * @template T of AppSeoUrlConfig|AppEntitySeoUrlConfig
     *
     * @param T $config
     *
     * @return AppFeature<T>
     */
    private static function feature(AppSeoUrlConfig|AppEntitySeoUrlConfig $config): AppFeature
    {
        return new AppFeature(
            appId: self::APP_ID,
            appName: self::APP_NAME,
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            config: $config,
        );
    }

    /**
     * @param list<string> $seoUrls
     * @param array<string, string> $entitySeoUrls
     */
    private static function manifest(array $seoUrls = [], array $entitySeoUrls = []): ManifestFixture
    {
        $manifest = ManifestFixture::empty()->withName(self::APP_NAME);

        foreach ($seoUrls as $name) {
            $manifest->withSeoUrl(SeoUrl::fromArray(['name' => $name, 'path' => ['en-GB' => $name]]));
        }

        foreach ($entitySeoUrls as $name => $entityName) {
            $manifest->withEntitySeoUrl(EntitySeoUrl::fromArray([
                'name' => $name,
                'entity' => $entityName,
                'defaultTemplate' => '{{ ' . $entityName . '.translated.name }}',
            ]));
        }

        return $manifest;
    }

    private static function template(string $routeName, ?string $salesChannelId = null): SeoUrlTemplateEntity
    {
        $template = new SeoUrlTemplateEntity();
        $template->setId(Uuid::randomHex());
        $template->setRouteName($routeName);
        $template->setSalesChannelId($salesChannelId);

        return $template;
    }

    private static function installContext(ManifestFixture $manifest): AppPersistContext
    {
        return AppFixture::createInstallContext(self::app(), $manifest);
    }

    private static function updateContext(ManifestFixture $manifest): AppPersistContext
    {
        return AppFixture::createUpdateContext(self::app(), $manifest);
    }

    private static function activationContext(): AppActivationContext
    {
        return new AppActivationContext(self::app(), Context::createDefaultContext());
    }

    private static function removalContext(bool $keepUserData = false): AppRemovalContext
    {
        return new AppRemovalContext(self::app(), Context::createDefaultContext(), $keepUserData);
    }

    private static function appUpdatedEvent(bool $active): AppUpdatedEvent
    {
        return new AppUpdatedEvent(self::app(active: $active), self::manifest(), Context::createDefaultContext());
    }

    private static function app(bool $active = true): AppEntity
    {
        return AppFixture::createAppEntity(self::APP_NAME, self::APP_ID, active: $active);
    }
}
