<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlUpdateListener;
use Shopware\Storefront\Framework\Seo\App\AppStaticSeoUrlSynchronizer;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlUpdateListener::class)]
class AppSeoUrlUpdateListenerTest extends TestCase
{
    private const PRODUCT_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const BLOG_ROUTE = 'storefront.app.SwagSeoUrlApp.blog-detail';

    /**
     * @var list<array{routeName: string, ids: list<string>}>
     */
    private array $updates = [];

    private SeoUrlUpdater $seoUrlUpdater;

    protected function setUp(): void
    {
        $this->updates = [];

        $seoUrlUpdater = static::createStub(SeoUrlUpdater::class);
        $seoUrlUpdater->method('update')->willReturnCallback(function (string $routeName, array $ids): void {
            /** @var list<string> $ids */
            $this->updates[] = ['routeName' => $routeName, 'ids' => $ids];
        });

        $this->seoUrlUpdater = $seoUrlUpdater;
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                EntityWrittenContainerEvent::class => 'updateAppSeoUrls',
                'sales_channel_domain.written' => 'syncStaticSeoUrls',
            ],
            AppSeoUrlUpdateListener::getSubscribedEvents()
        );
    }

    public function testWrittenEntitiesAreRegeneratedPerAppRoute(): void
    {
        $listener = $this->createListener([$this->route('product'), $this->route('ce_blog')]);

        $listener->updateAppSeoUrls($this->writtenEvent([
            'product' => [$this->writeResult('product', 'product-1'), $this->writeResult('product', 'product-2')],
            'ce_blog' => [$this->writeResult('ce_blog', 'blog-1')],
        ]));

        static::assertSame([
            ['routeName' => self::PRODUCT_ROUTE, 'ids' => ['product-1', 'product-2']],
            ['routeName' => self::BLOG_ROUTE, 'ids' => ['blog-1']],
        ], $this->updates);
    }

    public function testWrittenTranslationsRegenerateTheirParentEntity(): void
    {
        $listener = $this->createListener([$this->route('ce_blog')]);

        $listener->updateAppSeoUrls($this->writtenEvent([
            'ce_blog_translation' => [
                $this->writeResult('ce_blog_translation', ['ceBlogId' => 'blog-1', 'languageId' => 'language-1']),
            ],
        ]));

        static::assertSame([['routeName' => self::BLOG_ROUTE, 'ids' => ['blog-1']]], $this->updates);
    }

    public function testAnEntityWrittenWithItsTranslationIsRegeneratedOnce(): void
    {
        $listener = $this->createListener([$this->route('product')]);

        $listener->updateAppSeoUrls($this->writtenEvent([
            'product' => [$this->writeResult('product', 'product-1')],
            'product_translation' => [
                $this->writeResult('product_translation', ['productId' => 'product-1', 'languageId' => 'language-1']),
                $this->writeResult('product_translation', ['productId' => 'product-2', 'languageId' => 'language-1']),
            ],
        ]));

        static::assertSame([['routeName' => self::PRODUCT_ROUTE, 'ids' => ['product-1', 'product-2']]], $this->updates);
    }

    public function testWritesOfUnrelatedEntitiesAreIgnored(): void
    {
        $listener = $this->createListener([$this->route('product')]);

        $listener->updateAppSeoUrls($this->writtenEvent([
            'category' => [$this->writeResult('category', 'category-1')],
        ]));

        static::assertSame([], $this->updates);
    }

    public function testWithoutAppRoutesTheWrittenEventIsIgnored(): void
    {
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');

        $listener = new AppSeoUrlUpdateListener(
            $this->routeLoader([]),
            static::createStub(AppStaticSeoUrlSynchronizer::class),
            $seoUrlUpdater
        );

        $listener->updateAppSeoUrls($this->writtenEvent([
            'product' => [$this->writeResult('product', 'product-1')],
        ]));
    }

    public function testWrittenSalesChannelDomainsResynchroniseTheStaticUrls(): void
    {
        $synchronizer = $this->createMock(AppStaticSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('sync')->with(null);

        $listener = new AppSeoUrlUpdateListener($this->routeLoader([]), $synchronizer, $this->seoUrlUpdater);

        $listener->syncStaticSeoUrls();
    }

    /**
     * @param list<array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}> $routes
     */
    private function createListener(array $routes): AppSeoUrlUpdateListener
    {
        return new AppSeoUrlUpdateListener(
            $this->routeLoader($routes),
            static::createStub(AppStaticSeoUrlSynchronizer::class),
            $this->seoUrlUpdater
        );
    }

    /**
     * @param list<array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}> $routes
     */
    private function routeLoader(array $routes): AppSeoUrlRouteLoader
    {
        $loader = static::createStub(AppSeoUrlRouteLoader::class);
        $loader->method('getEntityRoutes')->willReturn($routes);

        return $loader;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     *
     * @return EntityWrittenContainerEvent<string|array<string, string>>
     */
    private function writtenEvent(array $identifiers): EntityWrittenContainerEvent
    {
        return EntityWrittenContainerEvent::createWithWrittenEvents($identifiers, Context::createDefaultContext(), []);
    }

    /**
     * @param string|array<string, string> $primaryKey
     */
    private function writeResult(string $entityName, string|array $primaryKey): EntityWriteResult
    {
        return new EntityWriteResult($primaryKey, [], $entityName, EntityWriteResult::OPERATION_UPDATE);
    }

    /**
     * @return array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}
     */
    private function route(string $entityName): array
    {
        return match ($entityName) {
            'product' => [
                'appId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'routeName' => self::PRODUCT_ROUTE,
                'hook' => 'product-teaser',
                'entityName' => 'product',
                'defaultTemplate' => '{{ product.translated.name }}',
            ],
            default => [
                'appId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'routeName' => self::BLOG_ROUTE,
                'hook' => 'blog-detail',
                'entityName' => 'ce_blog',
                'defaultTemplate' => '{{ ceBlog.translated.title }}',
            ],
        };
    }
}
