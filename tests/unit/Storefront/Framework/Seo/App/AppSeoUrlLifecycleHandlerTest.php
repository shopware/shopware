<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Shopware\Storefront\Framework\Seo\App\AppStaticSeoUrlSynchronizer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlLifecycleHandler::class)]
class AppSeoUrlLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const ROUTE_NAME = 'storefront.app.SwagSeoUrlApp.product-teaser';

    /**
     * @var list<array<int, mixed>>
     */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->calls = [];
    }

    public function testActivateInvalidatesTheRouteCacheBeforeSynchronisingAndRegenerating(): void
    {
        $handler = $this->createHandler(
            [$this->entityRoute()],
            StaticEntityRepository::of(ProductCollection::class, [['id-1', 'id-2'], ['id-3'], []], $this->productDefinition())
        );

        $handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([
            ['invalidate'],
            ['sync', self::APP_ID],
            ['update', self::ROUTE_NAME, ['id-1', 'id-2']],
            ['update', self::ROUTE_NAME, ['id-3']],
        ], $this->calls);
    }

    public function testEntitiesAreRegeneratedInChunks(): void
    {
        $limits = [];

        $repository = StaticEntityRepository::of(
            ProductCollection::class,
            [
                static function (Criteria $criteria) use (&$limits): array {
                    $limits[] = $criteria->getLimit();

                    return ['id-1'];
                },
                static function (Criteria $criteria) use (&$limits): array {
                    $limits[] = $criteria->getLimit();

                    return [];
                },
            ],
            $this->productDefinition()
        );

        $this->createHandler([$this->entityRoute()], $repository)
            ->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([500, 500], $limits);
    }

    public function testUpdateRegeneratesTheSeoUrlsOfAnActiveApp(): void
    {
        $handler = $this->createHandler(
            [$this->entityRoute()],
            StaticEntityRepository::of(ProductCollection::class, [['id-1'], []], $this->productDefinition())
        );

        $handler->update($this->persistContext(active: true));

        static::assertSame([
            ['invalidate'],
            ['sync', self::APP_ID],
            ['update', self::ROUTE_NAME, ['id-1']],
        ], $this->calls);
    }

    public function testUpdateSkipsAnInactiveApp(): void
    {
        $handler = $this->createHandler([$this->entityRoute()], StaticEntityRepository::of(ProductCollection::class, []));

        $handler->update($this->persistContext(active: false));

        static::assertSame([], $this->calls);
    }

    public function testRoutesBoundToAnUnknownEntityAreSkipped(): void
    {
        $handler = $this->createHandler(
            [[
                'appId' => self::APP_ID,
                'routeName' => 'storefront.app.SwagSeoUrlApp.blog-detail',
                'hook' => 'blog-detail',
                'entityName' => 'ce_blog',
                'defaultTemplate' => '{{ ceBlog.translated.title }}',
            ]],
            StaticEntityRepository::of(ProductCollection::class, [])
        );

        $handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([['invalidate'], ['sync', self::APP_ID]], $this->calls);
    }

    public function testWithoutEntityRoutesOnlyTheStaticUrlsAreSynchronised(): void
    {
        $handler = $this->createHandler([], StaticEntityRepository::of(ProductCollection::class, []));

        $handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([['invalidate'], ['sync', self::APP_ID]], $this->calls);
    }

    /**
     * @param list<array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}> $entityRoutes
     * @param StaticEntityRepository<ProductCollection> $repository
     */
    private function createHandler(array $entityRoutes, EntityRepository $repository): AppSeoUrlLifecycleHandler
    {
        $loader = static::createStub(AppSeoUrlRouteLoader::class);
        $loader->method('invalidateCache')->willReturnCallback(function (): void {
            $this->calls[] = ['invalidate'];
        });
        $loader->method('getEntityRoutes')->willReturn($entityRoutes);

        $synchronizer = static::createStub(AppStaticSeoUrlSynchronizer::class);
        $synchronizer->method('sync')->willReturnCallback(function (?string $appId): void {
            $this->calls[] = ['sync', $appId];
        });

        $updater = static::createStub(SeoUrlUpdater::class);
        $updater->method('update')->willReturnCallback(function (string $routeName, array $ids): void {
            $this->calls[] = ['update', $routeName, $ids];
        });

        $container = new Container();
        $container->set('product.repository', $repository);

        return new AppSeoUrlLifecycleHandler(
            $loader,
            $synchronizer,
            $updater,
            new DefinitionInstanceRegistry(
                $container,
                ['product' => ProductDefinition::class],
                ['product' => 'product.repository']
            )
        );
    }

    private function persistContext(bool $active): AppPersistContext
    {
        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $this->app($active),
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }

    private function app(bool $active = true): AppEntity
    {
        $app = new AppEntity();
        $app->setId(self::APP_ID);
        $app->setActive($active);

        return $app;
    }

    private function productDefinition(): ProductDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );

        $definition = $registry->getByEntityName(ProductDefinition::ENTITY_NAME);
        static::assertInstanceOf(ProductDefinition::class, $definition);

        return $definition;
    }

    /**
     * @return array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}
     */
    private function entityRoute(): array
    {
        return [
            'appId' => self::APP_ID,
            'routeName' => self::ROUTE_NAME,
            'hook' => 'product-teaser',
            'entityName' => 'product',
            'defaultTemplate' => '{{ product.translated.name }}',
        ];
    }
}
