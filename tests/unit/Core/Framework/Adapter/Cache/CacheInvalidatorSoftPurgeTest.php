<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(CacheInvalidator::class)]
#[Group('cache')]
class CacheInvalidatorSoftPurgeTest extends TestCase
{
    public function testSoftPurge(): void
    {
        $httpCacheStore = $this->createMock(TagAwareAdapterInterface::class);
        $httpCacheStore
            ->expects($this->once())
            ->method('saveDeferred');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            'prod',
            $httpCacheStore,
            true
        );

        $invalidator->invalidate(['foo'], true);
    }

    public function testSoftPurgeIsSkipped(): void
    {
        $httpCacheStore = $this->createMock(TagAwareAdapterInterface::class);
        $httpCacheStore
            ->expects($this->never())
            ->method('saveDeferred');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->once())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            'prod',
            $httpCacheStore,
            false
        );

        $invalidator->invalidate(['foo']);
    }
}
