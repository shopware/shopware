<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheInvalidationSubscriber::class)]
class EmptyMediaCacheInvalidationTest extends TestCase
{
    public function testEmptyMediaEventDoesNotConnectToTheDatabaseOrInvalidateTags(): void
    {
        $driver = static::createMock(Driver::class);
        $driver->expects($this->never())->method('connect');
        $invalidator = static::createMock(CacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidate');
        $subscriber = new CacheInvalidationSubscriber(
            $invalidator,
            new Connection([], $driver),
            productStreamIndexerEnabled: true,
        );

        $subscriber->invalidateMedia(new MediaIndexerEvent([], Context::createDefaultContext()));
    }
}
