<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
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
        $cacheInvalidator->expects(static::once())
            ->method('invalidate')
            ->with(
                [
                    'context-factory-' . $salesChannelId,
                    'base-context-factory-' . $salesChannelId,
                ],
                false
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            false,
            false
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
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())
            ->method('invalidate')
            ->with(
                $tags,
                false
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            $enabled,
            $enabled
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
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())
            ->method('invalidate')
            ->with(
                $tags,
                false
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            $enabled,
            $enabled
        );

        $subscriber->invalidateConfigKey(new SystemConfigChangedHook(['test' => '1'], []));
    }

    public function testInvalidateMediaWithoutVariantsWillInvalidateOnlyProducts(): void
    {
        $productId = '123';
        $event = new MediaIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), []);

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            false,
            false
        );
        $this->connection->method('fetchAllAssociative')
            ->willReturn([['product_id' => $productId, 'version_id' => null]]);

        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->expects(static::once())
                ->method('invalidate')
                ->with(
                    [
                        EntityCacheKeyGenerator::buildProductTag($productId),
                    ],
                    false
                );
        } else {
            $this->cacheInvalidator->expects(static::once())
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
        $productId = '123';
        $variants = ['456', '789'];
        $event = new MediaIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), []);

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            false,
            false
        );
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['product_id' => $productId, 'variant_id' => $variants[0]],
                ['product_id' => $productId, 'variant_id' => $variants[1]],
            ]);

        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->expects(static::once())
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
            $this->cacheInvalidator->expects(static::once())
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
