<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\ServiceSkipList;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(ServiceSkipList::class)]
class ServiceSkipListTest extends TestCase
{
    public function testServiceIsSkippedOnceRecorded(): void
    {
        $skipList = new ServiceSkipList(new StaticSystemConfigService([]), new MockClock('2026-01-01 00:00:00'));

        static::assertFalse($skipList->shouldSkip('SwagService'));

        $skipList->skip('SwagService');

        static::assertTrue($skipList->shouldSkip('SwagService'));
        static::assertFalse($skipList->shouldSkip('OtherService'));
    }

    public function testEntryExpiresAfterTheBackstopWindow(): void
    {
        $clock = new MockClock('2026-01-01 00:00:00');
        $skipList = new ServiceSkipList(new StaticSystemConfigService([]), $clock);

        $skipList->skip('SwagService');
        static::assertTrue($skipList->shouldSkip('SwagService'));

        $clock->sleep(ServiceSkipList::BACKSTOP_SECONDS + 1);

        static::assertFalse($skipList->shouldSkip('SwagService'));
    }

    public function testClearForgetsEverything(): void
    {
        $skipList = new ServiceSkipList(new StaticSystemConfigService([]), new MockClock('2026-01-01 00:00:00'));

        $skipList->skip('SwagService');
        $skipList->clear();

        static::assertFalse($skipList->shouldSkip('SwagService'));
    }
}
