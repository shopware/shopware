<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Message\InvalidateMediaCache;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InvalidateMediaCache::class)]
class InvalidateMediaCacheTest extends TestCase
{
    public function testSynchronousDispatchDeduplicatesMediaIds(): void
    {
        $bus = static::createMock(MessageBusInterface::class);
        $message = new InvalidateMediaCache(['first', 'second']);
        $bus->expects($this->once())->method('dispatch')
            ->with($message, [new TransportNamesStamp([])])
            ->willReturn(new Envelope($message));

        InvalidateMediaCache::dispatch($bus, ['first', 'first', 'second'], Context::createDefaultContext());
    }

    public function testEmptyWriteSkipsDispatch(): void
    {
        $bus = static::createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        InvalidateMediaCache::dispatch($bus, [], Context::createDefaultContext());
    }
}
