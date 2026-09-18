<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlUpdateListener;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;

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

    private CollectingMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->updates = [];
        $this->messageBus = new CollectingMessageBus();

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
        $listener = $this->listener($this->route('product'), $this->route('ce_blog'));

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
        $listener = $this->listener($this->route('ce_blog'));

        $listener->updateAppSeoUrls($this->writtenEvent([
            'ce_blog_translation' => [
                $this->writeResult('ce_blog_translation', ['ceBlogId' => 'blog-1', 'languageId' => 'language-1']),
            ],
        ]));

        static::assertSame([['routeName' => self::BLOG_ROUTE, 'ids' => ['blog-1']]], $this->updates);
    }

    public function testAnEntityWrittenWithItsTranslationIsRegeneratedOnce(): void
    {
        $listener = $this->listener($this->route('product'));

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
        $listener = $this->listener($this->route('product'));

        $listener->updateAppSeoUrls($this->writtenEvent([
            'category' => [$this->writeResult('category', 'category-1')],
        ]));

        static::assertSame([], $this->updates);
    }

    public function testWithoutAppRoutesTheWrittenEventIsIgnored(): void
    {
        $listener = $this->listener();

        $listener->updateAppSeoUrls($this->writtenEvent([
            'product' => [$this->writeResult('product', 'product-1')],
        ]));

        static::assertSame([], $this->updates);
    }

    public function testWrittenSalesChannelDomainsRequestAStaticSync(): void
    {
        $this->listener()->syncStaticSeoUrls();

        static::assertCount(1, $this->messageBus->getMessages());
        static::assertEquals(
            new AppSeoUrlSyncMessage(),
            $this->messageBus->getMessages()[0]->getMessage()
        );
    }

    private function listener(AppSeoUrlRouteEntity ...$routes): AppSeoUrlUpdateListener
    {
        $provider = static::createStub(AppSeoUrlRouteProvider::class);
        $provider->method('getEntityRoutes')->willReturn(new EntityCollection($routes));

        return new AppSeoUrlUpdateListener($provider, $this->seoUrlUpdater, $this->messageBus);
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

    private function route(string $entityName): AppSeoUrlRouteEntity
    {
        $name = $entityName === 'product' ? 'product-teaser' : 'blog-detail';

        $route = new AppSeoUrlRouteEntity();
        $route->id = Uuid::randomHex();
        $route->appId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $route->name = $name;
        $route->routeName = 'storefront.app.SwagSeoUrlApp.' . $name;
        $route->hook = $name;
        $route->entityName = $entityName;
        $route->defaultTemplate = '{{ ' . $entityName . '.translated.name }}';
        $route->setUniqueIdentifier($route->id);

        return $route;
    }
}
