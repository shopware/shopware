<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;

/**
 * @internal
 *
 * @group cache
 *
 * @covers \Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber
 */
class CacheInvalidationSubscriberTest extends TestCase
{
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
     *
     * @dataProvider provideTracingTranslationExamples
     */
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
     *
     * @dataProvider provideTracingConfigExamples
     */
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
