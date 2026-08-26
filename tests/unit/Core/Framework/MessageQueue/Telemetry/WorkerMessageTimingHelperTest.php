<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkerMessageTimingHelper::class)]
class WorkerMessageTimingHelperTest extends TestCase
{
    public function testElapsedMsReturnsElapsedMillisecondsAfterStart(): void
    {
        $helper = new WorkerMessageTimingHelper();
        $message = new \stdClass();

        $helper->start($message);
        $elapsed = $helper->elapsedMs($message);

        static::assertIsFloat($elapsed);
        static::assertGreaterThanOrEqual(0.0, $elapsed);
    }

    public function testElapsedMsReturnsNullWhenStartWasNeverRecorded(): void
    {
        $helper = new WorkerMessageTimingHelper();

        static::assertNull($helper->elapsedMs(new \stdClass()));
    }

    public function testElapsedMsIsNonConsuming(): void
    {
        $helper = new WorkerMessageTimingHelper();
        $message = new \stdClass();

        $helper->start($message);

        // messenger and scheduled-task collectors read the same entry independently of listener order
        $first = $helper->elapsedMs($message);
        $second = $helper->elapsedMs($message);

        static::assertIsFloat($first);
        static::assertIsFloat($second);
        static::assertGreaterThanOrEqual($first, $second);
    }

    public function testTracksEachMessageIndependently(): void
    {
        $helper = new WorkerMessageTimingHelper();
        $first = new \stdClass();
        $second = new \stdClass();

        $helper->start($first);
        $helper->start($second);

        // reading one message must not consume the other message's entry
        static::assertIsFloat($helper->elapsedMs($first));
        static::assertIsFloat($helper->elapsedMs($second));
    }
}
