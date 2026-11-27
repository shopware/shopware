<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;

/**
 * @internal
 */
#[CoversClass(CacheInvalidationSubscriber::class)]
#[Group('cache')]
class CacheInvalidationSubscriberTest extends TestCase
{
    /**
     * @var CacheInvalidator&MockObject
     */
    private CacheInvalidator $cacheInvalidator;

    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->connection = $this->createMock(Connection::class);
    }

    public function testConsidersKeyOfCachedBaseContextFactoryForInvalidatingContext(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    'context-factory-' . $salesChannelId,
                    'base-context-factory-' . $salesChannelId,
                ],
                true
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            false,
            false,
            true
        );

        $subscriber->invalidateContext(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SalesChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $salesChannelId,
                            [],
                            SalesChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    /**
     * @param array<string> $tags
     */
    #[DataProvider('provideTracingTranslationExamples')]
    public function testInvalidateTranslation(bool $enabled, array $tags): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                $tags,
                false
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            $enabled,
            $enabled,
            true
        );

        $event = $this->createSnippetEvent();

        $subscriber->invalidateSnippets($event);
    }

    public static function provideTracingTranslationExamples(): \Generator
    {
        yield 'enabled' => [
            false,
            [
                'shopware.translator',
            ],
        ];

        yield 'disabled' => [
            true,
            [
                'translator.test',
            ],
        ];
    }

    /**
     * @param array<string> $tags
     */
    #[DataProvider('provideTracingConfigExamples')]
    public function testInvalidateConfig(bool $enabled, array $tags): void
    {
        Feature::skipTestIfActive('cache_rework', $this);

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                $tags,
                false
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            $enabled,
            $enabled,
            true
        );

        $subscriber->invalidateConfigKey(new SystemConfigChangedHook(['test' => '1'], []));
    }

    public function testInvalidateMediaWithoutVariantsWillInvalidateOnlyProducts(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $event = new MediaIndexerEvent([$mediaId], Context::createDefaultContext(), []);

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            false,
            false,
            true
        );
        $this->connection->method('fetchAllAssociative')
            ->willReturn([['product_id' => $productId, 'version_id' => null]]);

        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->expects($this->once())
                ->method('invalidate')
                ->with(
                    [
                        EntityCacheKeyGenerator::buildProductTag($productId),
                    ],
                    false
                );
        } else {
            $this->cacheInvalidator->expects($this->once())
                ->method('invalidate')
                ->with(
                    [
                        'product-detail-route-' . $productId,
                    ],
                    false
                );
        }

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateMediaWithVariantsWillInvalidateProductsAndVariants(): void
    {
        $productId = Uuid::randomHex();
        $variants = [Uuid::randomHex(), Uuid::randomHex()];
        $mediaId = Uuid::randomHex();
        $event = new MediaIndexerEvent([$mediaId], Context::createDefaultContext(), []);

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            false,
            false,
            true
        );
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['product_id' => $productId, 'variant_id' => $variants[0]],
                ['product_id' => $productId, 'variant_id' => $variants[1]],
            ]);

        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->expects($this->once())
                ->method('invalidate')
                ->with(
                    [
                        EntityCacheKeyGenerator::buildProductTag($productId),
                        EntityCacheKeyGenerator::buildProductTag($variants[0]),
                        EntityCacheKeyGenerator::buildProductTag($variants[1]),
                    ],
                    false
                );
        } else {
            $this->cacheInvalidator->expects($this->once())
                ->method('invalidate')
                ->with(
                    [
                        'product-detail-route-' . $productId,
                        'product-detail-route-' . $variants[0],
                        'product-detail-route-' . $variants[1],
                    ],
                    false
                );
        }

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateMediaInvalidatesEveryPageShowingTheMedia(): void
    {
        $ids = [
            'product' => Uuid::randomHex(),
            'listing' => Uuid::randomHex(),
            'stream' => Uuid::randomHex(),
            'category' => Uuid::randomHex(),
            'override-category' => Uuid::randomHex(),
            'element-page' => Uuid::randomHex(),
            'section-page' => Uuid::randomHex(),
            'block-section' => Uuid::randomHex(),
            'block-page' => Uuid::randomHex(),
            'landing-page' => Uuid::randomHex(),
            'override-variant' => Uuid::randomHex(),
            'override-parent' => Uuid::randomHex(),
            'manufacturer' => Uuid::randomHex(),
        ];

        // every lookup is answered by the table it reads
        $results = [
            'FROM product_category_tree' => [$ids['listing']],
            'FROM product_stream_mapping' => [$ids['stream']],
            'FROM category WHERE media_id' => [$ids['category']],
            'FROM category_translation_media_usage' => [$ids['override-category']],
            'FROM cms_page_media_usage' => [$ids['element-page']],
            'FROM cms_section WHERE background_media_id' => [$ids['section-page']],
            'FROM cms_block WHERE background_media_id' => [$ids['block-section']],
            'FROM cms_section WHERE id IN' => [$ids['block-page']],
            'FROM landing_page_translation_media_usage' => [$ids['landing-page']],
            'FROM product_translation_media_usage' => [$ids['override-variant']],
            'FROM product WHERE id IN' => [$ids['override-parent']],
            'FROM product_manufacturer WHERE media_id' => [$ids['manufacturer']],
        ];

        $this->connection->expects($this->once())->method('fetchAllAssociative')->willReturn([['product_id' => $ids['product'], 'variant_id' => null]]);
        $this->connection->method('fetchFirstColumn')->willReturnCallback(static function (string $sql) use ($results): array {
            foreach ($results as $table => $result) {
                if (str_contains($sql, $table)) {
                    return $result;
                }
            }

            return [];
        });

        $tags = [];
        $this->cacheInvalidator->expects($this->once())->method('invalidate')->willReturnCallback(static function (array $invalidated) use (&$tags): void {
            $tags = $invalidated;
        });

        $subscriber = new CacheInvalidationSubscriber($this->cacheInvalidator, $this->connection, false, false, true);
        $subscriber->invalidateMedia(new MediaIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), []));

        $detail = Feature::isActive('cache_rework') ? ProductDetailRoute::buildName(...) : CachedProductDetailRoute::buildName(...);
        $listing = Feature::isActive('cache_rework') ? ProductListingRoute::buildName(...) : CachedProductListingRoute::buildName(...);

        foreach ([
            $detail($ids['product']),
            $listing($ids['listing']),
            EntityCacheKeyGenerator::buildStreamTag($ids['stream']),
            CategoryRoute::buildName($ids['category']),
            CategoryRoute::buildName($ids['override-category']),
            EntityCacheKeyGenerator::buildCmsTag($ids['element-page']),
            EntityCacheKeyGenerator::buildCmsTag($ids['section-page']),
            EntityCacheKeyGenerator::buildCmsTag($ids['block-page']),
            LandingPageRoute::buildName($ids['landing-page']),
            $detail($ids['override-parent']),
            EntityCacheKeyGenerator::buildManufacturerTag($ids['manufacturer']),
        ] as $tag) {
            static::assertContains($tag, $tags);
        }

        static::assertNotContains($detail($ids['override-variant']), $tags, 'the detail page of a variant is tagged with its parent');
    }

    public static function provideTracingConfigExamples(): \Generator
    {
        yield 'enabled' => [
            false,
            [
                'global.system.config',
                'system-config',
            ],
        ];

        yield 'disabled' => [
            true,
            [
                'config.test',
                'system-config',
            ],
        ];
    }

    public function createSnippetEvent(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SnippetDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            [
                                'translationKey' => 'test',
                            ],
                            SnippetDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        );
    }
}
