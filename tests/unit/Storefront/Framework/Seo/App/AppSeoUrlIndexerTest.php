<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlIndexer;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlIndexingMessage;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlIndexer::class)]
class AppSeoUrlIndexerTest extends TestCase
{
    private const PRODUCT_TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const PRODUCT_DETAIL_ROUTE = 'storefront.app.SwagSeoUrlApp.product-detail';

    private const CATEGORY_TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.category-teaser';

    private const PRODUCT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_PRODUCT_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const CATEGORY_ID = 'cccccccccccccccccccccccccccccccc';

    private const ENTITY_ID = 'ffffffffffffffffffffffffffffffff';

    private const ENGLISH_ID = 'dddddddddddddddddddddddddddddddd';

    private const GERMAN_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private CollectingMessageBus $messageBus;

    private SeoUrlUpdater $seoUrlUpdater;

    /**
     * @var list<array{routeName: string, ids: list<string>}>
     */
    private array $updatedSeoUrls = [];

    protected function setUp(): void
    {
        $this->messageBus = new CollectingMessageBus();
        $this->updatedSeoUrls = [];

        $seoUrlUpdater = static::createStub(SeoUrlUpdater::class);
        $seoUrlUpdater->method('update')->willReturnCallback(function (string $routeName, array $ids): void {
            $this->updatedSeoUrls[] = ['routeName' => $routeName, 'ids' => array_values($ids)];
        });
        $this->seoUrlUpdater = $seoUrlUpdater;
    }

    public function testTheIndexerIsNamedAfterAppSeoUrls(): void
    {
        static::assertSame('app_seo_url.indexer', $this->indexer()->getName());
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    #[DataProvider('iterationOffsets')]
    public function testAFullIndexRunHandsOutOneEntityRoutePerMessage(?array $offset, string $expectedRouteName, int $expectedNextPosition): void
    {
        $indexer = $this->indexer(
            $this->entitySeoUrl('product-teaser', 'product'),
            $this->entitySeoUrl('category-teaser', 'category'),
        );

        $message = $indexer->iterate($offset);

        static::assertNotNull($message);
        static::assertNotInstanceOf(AppSeoUrlIndexingMessage::class, $message);
        static::assertSame([$expectedRouteName], $message->getData());
        static::assertSame(['offset' => $expectedNextPosition], $message->getOffset());
    }

    /**
     * @return iterable<string, array{array{offset: int|null}|null, string, int}>
     */
    public static function iterationOffsets(): iterable
    {
        yield 'a new run starts with the first route' => [null, self::PRODUCT_TEASER_ROUTE, 1];
        yield 'an offset without a position starts with the first route' => [['offset' => null], self::PRODUCT_TEASER_ROUTE, 1];
        yield 'the offset of the previous message continues with the next route' => [['offset' => 1], self::CATEGORY_TEASER_ROUTE, 2];
    }

    public function testAFullIndexRunEndsAfterTheLastEntityRoute(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        static::assertNull($indexer->iterate(['offset' => 1]));
    }

    public function testWithoutEntityRoutesAFullIndexRunHasNothingToDo(): void
    {
        static::assertNull($this->indexer()->iterate(null));
    }

    public function testTheTotalIsTheNumberOfEntityRoutes(): void
    {
        $indexer = $this->indexer(
            $this->entitySeoUrl('product-teaser', 'product'),
            $this->entitySeoUrl('category-teaser', 'category'),
        );

        static::assertSame(2, $indexer->getTotal());
    }

    public function testTheWrittenIdsOfEveryBoundEntityAreCollected(): void
    {
        $context = Context::createDefaultContext();
        $indexer = $this->indexer(
            $this->entitySeoUrl('product-teaser', 'product'),
            $this->entitySeoUrl('category-teaser', 'category'),
        );

        $message = $indexer->update($this->writtenEvent([
            'product' => [self::PRODUCT_ID, self::OTHER_PRODUCT_ID],
            'category' => [self::CATEGORY_ID],
        ], $context));

        static::assertInstanceOf(AppSeoUrlIndexingMessage::class, $message);
        static::assertSame(
            ['product' => [self::PRODUCT_ID, self::OTHER_PRODUCT_ID], 'category' => [self::CATEGORY_ID]],
            $message->getIdsByEntity()
        );
        static::assertSame([self::PRODUCT_ID, self::OTHER_PRODUCT_ID, self::CATEGORY_ID], $message->getData());
        static::assertSame($context, $message->getContext());
    }

    #[DataProvider('translatedEntities')]
    public function testWrittenTranslationsAreCollectedAsTheirParentEntity(string $entityName, string $parentKey): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('teaser', $entityName));

        $message = $indexer->update($this->writtenEvent([
            $entityName . '_translation' => [[$parentKey => self::ENTITY_ID, 'languageId' => self::GERMAN_ID]],
        ]));

        static::assertInstanceOf(AppSeoUrlIndexingMessage::class, $message);
        static::assertSame([self::ENTITY_ID], $message->getIds($entityName));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function translatedEntities(): iterable
    {
        yield 'a product translation belongs to its product' => ['product', 'productId'];
        yield 'a translation of a snake cased entity is keyed by its camel cased name' => ['landing_page', 'landingPageId'];
        yield 'a custom entity translation is keyed by its camel cased name' => ['ce_blog', 'ceBlogId'];
    }

    public function testAnEntityWrittenTogetherWithItsTranslationsIsCollectedOnce(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        $message = $indexer->update($this->writtenEvent([
            'product' => [self::PRODUCT_ID],
            'product_translation' => [
                ['productId' => self::PRODUCT_ID, 'languageId' => self::ENGLISH_ID],
                ['productId' => self::PRODUCT_ID, 'languageId' => self::GERMAN_ID],
                ['productId' => self::OTHER_PRODUCT_ID, 'languageId' => self::GERMAN_ID],
            ],
        ]));

        static::assertInstanceOf(AppSeoUrlIndexingMessage::class, $message);
        static::assertSame([self::PRODUCT_ID, self::OTHER_PRODUCT_ID], $message->getIds('product'));
    }

    public function testRoutesSharingAnEntityCollectItsIdsOnce(): void
    {
        $indexer = $this->indexer(
            $this->entitySeoUrl('product-teaser', 'product'),
            $this->entitySeoUrl('product-detail', 'product'),
        );

        $message = $indexer->update($this->writtenEvent(['product' => [self::PRODUCT_ID]]));

        static::assertInstanceOf(AppSeoUrlIndexingMessage::class, $message);
        static::assertSame(['product' => [self::PRODUCT_ID]], $message->getIdsByEntity());
        static::assertSame([self::PRODUCT_ID], $message->getData());
    }

    public function testDeletedEntitiesAreCollectedLikeWrittenOnes(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        $message = $indexer->update(EntityWrittenContainerEvent::createWithDeletedEvents(
            ['product' => [new EntityWriteResult(self::PRODUCT_ID, [], 'product', EntityWriteResult::OPERATION_DELETE)]],
            Context::createDefaultContext(),
            []
        ));

        static::assertInstanceOf(AppSeoUrlIndexingMessage::class, $message);
        static::assertSame([self::PRODUCT_ID], $message->getIds('product'));
    }

    public function testWritesOfEntitiesNoRouteIsBoundToAreIgnored(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        static::assertNull($indexer->update($this->writtenEvent([
            'category' => [self::CATEGORY_ID],
            'category_translation' => [['categoryId' => self::CATEGORY_ID, 'languageId' => self::GERMAN_ID]],
        ])));
    }

    public function testWithoutEntityRoutesEveryWriteIsIgnored(): void
    {
        static::assertNull($this->indexer()->update($this->writtenEvent(['product' => [self::PRODUCT_ID]])));
    }

    /**
     * @param string|array<string, string> $primaryKey
     */
    #[DataProvider('primaryKeysWithoutAnEntityId')]
    public function testWriteResultsWithoutAnIdOfTheBoundEntityAreIgnored(string $writtenEntity, string|array $primaryKey): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        static::assertNull($indexer->update($this->writtenEvent([$writtenEntity => [$primaryKey]])));
    }

    /**
     * @return iterable<string, array{string, string|array<string, string>}>
     */
    public static function primaryKeysWithoutAnEntityId(): iterable
    {
        yield 'a composite key on the entity itself' => ['product', ['productId' => self::PRODUCT_ID, 'languageId' => self::GERMAN_ID]];
        yield 'a translation key without the id of its parent' => ['product_translation', ['languageId' => self::GERMAN_ID]];
        yield 'a translation key that is not composite' => ['product_translation', self::PRODUCT_ID];
    }

    public function testWrittenIdsRegenerateTheSeoUrlsOfEveryRouteBoundToTheirEntity(): void
    {
        $indexer = $this->indexer(
            $this->entitySeoUrl('product-teaser', 'product'),
            $this->entitySeoUrl('category-teaser', 'category'),
            $this->entitySeoUrl('product-detail', 'product'),
            $this->entitySeoUrl('blog-detail', 'ce_blog'),
        );

        $message = new AppSeoUrlIndexingMessage([self::PRODUCT_ID, self::OTHER_PRODUCT_ID, self::CATEGORY_ID]);
        $message->setIdsByEntity([
            'product' => [self::PRODUCT_ID, self::OTHER_PRODUCT_ID],
            'category' => [self::CATEGORY_ID],
        ]);

        $indexer->handle($message);

        static::assertSame([
            ['routeName' => self::PRODUCT_TEASER_ROUTE, 'ids' => [self::PRODUCT_ID, self::OTHER_PRODUCT_ID]],
            ['routeName' => self::CATEGORY_TEASER_ROUTE, 'ids' => [self::CATEGORY_ID]],
            ['routeName' => self::PRODUCT_DETAIL_ROUTE, 'ids' => [self::PRODUCT_ID, self::OTHER_PRODUCT_ID]],
        ], $this->updatedSeoUrls);
        static::assertSame([], $this->dispatchedMessages());
    }

    public function testAFullIndexMessageRebuildsItsRouteThroughTheTemplateIndexingChain(): void
    {
        $indexer = $this->indexer(
            $this->entitySeoUrl('product-teaser', 'product'),
            $this->entitySeoUrl('category-teaser', 'category'),
        );

        $indexer->handle(new EntityIndexingMessage([self::CATEGORY_TEASER_ROUTE], ['offset' => 2]));

        static::assertEquals(
            [new SeoUrlTemplateIndexingMessage(self::CATEGORY_TEASER_ROUTE, 'category')],
            $this->dispatchedMessages()
        );
        static::assertSame([], $this->updatedSeoUrls);
    }

    public function testARouteRemovedSinceTheFullIndexRunStartedIsNotRebuilt(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        $indexer->handle(new EntityIndexingMessage([self::CATEGORY_TEASER_ROUTE], ['offset' => 2]));

        static::assertSame([], $this->dispatchedMessages());
    }

    public function testAMessageWithoutAListOfRouteNamesIsIgnored(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));

        $indexer->handle(new EntityIndexingMessage(self::PRODUCT_TEASER_ROUTE));

        static::assertSame([], $this->dispatchedMessages());
        static::assertSame([], $this->updatedSeoUrls);
    }

    public function testTheSeoUrlUpdaterIsTheOnlyOptionToSkip(): void
    {
        static::assertSame(['app_seo_url.seo-url'], $this->indexer()->getOptions());
    }

    #[DataProvider('messagesOfBothKinds')]
    public function testASkippedSeoUrlUpdaterRegeneratesNothing(EntityIndexingMessage $message): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));
        $message->addSkip(AppSeoUrlIndexer::SEO_URL_UPDATER);

        $indexer->handle($message);

        static::assertSame([], $this->updatedSeoUrls);
        static::assertSame([], $this->dispatchedMessages());
    }

    /**
     * @return iterable<string, array{EntityIndexingMessage}>
     */
    public static function messagesOfBothKinds(): iterable
    {
        $writtenEntities = new AppSeoUrlIndexingMessage([self::PRODUCT_ID]);
        $writtenEntities->setIdsByEntity(['product' => [self::PRODUCT_ID]]);

        yield 'a message of written entities' => [$writtenEntities];
        yield 'a message of a full index run' => [new EntityIndexingMessage([self::PRODUCT_TEASER_ROUTE], ['offset' => 1])];
    }

    public function testSkippingAnotherUpdaterStillRegeneratesTheSeoUrls(): void
    {
        $indexer = $this->indexer($this->entitySeoUrl('product-teaser', 'product'));
        $message = new AppSeoUrlIndexingMessage([self::PRODUCT_ID]);
        $message->setIdsByEntity(['product' => [self::PRODUCT_ID]]);
        $message->addSkip('product.seo-url');

        $indexer->handle($message);

        static::assertSame([['routeName' => self::PRODUCT_TEASER_ROUTE, 'ids' => [self::PRODUCT_ID]]], $this->updatedSeoUrls);
    }

    public function testTheIndexerCannotBeDecorated(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(AppSeoUrlIndexer::class));

        $this->indexer()->getDecorated();
    }

    private function indexer(AppEntitySeoUrlConfig ...$entitySeoUrls): AppSeoUrlIndexer
    {
        $routes = static::createStub(AppSeoUrlRouteProvider::class);
        $routes->method('getEntityRoutes')->willReturn($entitySeoUrls);

        return new AppSeoUrlIndexer($routes, $this->seoUrlUpdater, $this->messageBus);
    }

    private function entitySeoUrl(string $name, string $entityName): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            name: $name,
            routeName: 'storefront.app.SwagSeoUrlApp.' . $name,
            hook: $name,
            entityName: $entityName,
            defaultTemplate: '{{ ' . $entityName . '.translated.name }}',
        );
    }

    /**
     * @param array<string, list<string|array<string, string>>> $primaryKeysByEntity
     */
    private function writtenEvent(array $primaryKeysByEntity, ?Context $context = null): EntityWrittenContainerEvent
    {
        $writeResults = [];

        foreach ($primaryKeysByEntity as $entityName => $primaryKeys) {
            $writeResults[$entityName] = array_map(
                static fn (string|array $primaryKey): EntityWriteResult => new EntityWriteResult($primaryKey, [], $entityName, EntityWriteResult::OPERATION_UPDATE),
                $primaryKeys
            );
        }

        return EntityWrittenContainerEvent::createWithWrittenEvents($writeResults, $context ?? Context::createDefaultContext(), []);
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
}
