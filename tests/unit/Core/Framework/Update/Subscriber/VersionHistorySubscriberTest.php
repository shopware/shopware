<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Services\VersionHistory;
use Shopware\Core\Framework\Update\Subscriber\VersionHistorySubscriber;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(VersionHistorySubscriber::class)]
class VersionHistorySubscriberTest extends TestCase
{
    public function testRecordsInstallsAndUpdates(): void
    {
        $storage = new ArrayKeyValueStorage();
        $clock = new MockClock();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new VersionHistorySubscriber(new VersionHistory($storage, $clock, '6.7.16.0')));
        $dispatcher->dispatch(new SystemInstallCompletedEvent(Context::createDefaultContext()));

        $updatedVersionHistory = new VersionHistory($storage, $clock, '6.7.17.0');
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new VersionHistorySubscriber($updatedVersionHistory));
        $dispatcher->dispatch(new UpdatePostFinishEvent(Context::createDefaultContext(), '6.7.16.0', '6.7.17.0'));

        static::assertSame(
            [['6.7.16.0', 'install'], ['6.7.17.0', 'update']],
            array_map(static fn (array $entry) => [$entry['version'], $entry['type']], $updatedVersionHistory->all()),
        );
    }
}
