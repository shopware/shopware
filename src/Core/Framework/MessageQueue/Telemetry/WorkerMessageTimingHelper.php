<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Tracks the wall time a message spends between being received and reaching a terminal worker event (handled or failed).
 * The worker processes one message at a time, but received/handled/failed arrive as separate events, so the receive timestamp
 * has to be parked and read back later.
 *
 * Timestamps are kept in a {@see \WeakMap} keyed by the message object: when a failed message is retried it is
 * re-received as a fresh object with its own entry, and any orphaned entry (e.g. a message the worker decided not to handle)
 * is dropped automatically once the message is garbage-collected (no unbounded growth in a long-running worker.)
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class WorkerMessageTimingHelper
{
    /**
     * @var \WeakMap<object, float|int>
     */
    private \WeakMap $startTimes;

    public function __construct()
    {
        $this->startTimes = new \WeakMap();
    }

    public function start(object $message): void
    {
        /** @phpstan-ignore typePerfect.noArrayAccessOnObject (array access is the idiomatic \WeakMap API; the rule flags it only because it does not yet exempt ArrayAccess containers) */
        $this->startTimes[$message] = hrtime(true);
    }

    /**
     * Returns the elapsed milliseconds since {@see start()} for the message and clears the entry, or `null`
     * when no start was recorded (start never ran for this message).
     */
    public function elapsedMs(object $message): ?float
    {
        /** @phpstan-ignore typePerfect.noArrayAccessOnObject (array access is the idiomatic \WeakMap API; the rule flags it only because it does not yet exempt ArrayAccess containers) */
        $start = $this->startTimes[$message] ?? null;
        if ($start === null) {
            return null;
        }

        /** @phpstan-ignore typePerfect.noArrayAccessOnObject (array access is the idiomatic \WeakMap API; the rule flags it only because it does not yet exempt ArrayAccess containers) */
        unset($this->startTimes[$message]);

        return (hrtime(true) - $start) / 1_000_000;
    }
}
