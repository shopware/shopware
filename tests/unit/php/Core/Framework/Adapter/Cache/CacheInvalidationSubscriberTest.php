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
            $this->createMock(Connection::class)
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
}
