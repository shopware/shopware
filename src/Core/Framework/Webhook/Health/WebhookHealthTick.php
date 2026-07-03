<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * The clock for the webhook health model, pulsed by the delivery worker's transport polling.
 * The scheduler's cadence is host-controlled (cron or admin-worker, typically minutes to tens
 * of minutes), so a scheduled task cannot honour a 60 s tick — while the worker polling the
 * webhook transport is, by definition, alive exactly when health has decisions to make.
 *
 * Every poll calls {@see run()}; an in-memory debounce reduces that to one tick per interval
 * per worker. There is deliberately no cross-worker election: every clocked duty is a guarded
 * single statement or a per-webhook FOR UPDATE transaction, so overlapping runners are
 * absorbed — a few redundant indexed scans per interval, never a wrong transition.
 *
 * The heartbeat key is written only after a tick that ran to completion and is what the
 * health-status endpoint reports: a tick that keeps failing shows up as a stale heartbeat,
 * not as a dead delivery worker.
 *
 * 60 s gives the smallest cooldown tier (300 s) 5x headroom.
 *
 * @internal
 */
#[Package('framework')]
class WebhookHealthTick
{
    public const INTERVAL_SECONDS = 60;

    /**
     * `app_config` key holding the completion time of the last health tick, in
     * `Defaults::STORAGE_DATE_TIME_FORMAT`. Written fleet-wide by whichever worker
     * ticked last; absent until the first completed tick.
     */
    public const HEARTBEAT_STORAGE_KEY = 'webhook.health.tick.completed_at';

    private ?\DateTimeImmutable $nextAttemptAt = null;

    public function __construct(
        private readonly AbstractKeyValueStorage $keyValueStorage,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly ?EndpointLifecycle $lifecycle = null,
    ) {
    }

    /**
     * Never throws: an exception escaping into the Messenger worker loop would stop delivery
     * over a health-side bug. This is a bulkhead between the two planes, not a swallowed
     * invariant — the skipped heartbeat write makes every failure observable.
     */
    public function run(): void
    {
        if ($this->lifecycle === null) {
            return;
        }

        $now = $this->clock->now();
        if ($this->nextAttemptAt !== null && $now < $this->nextAttemptAt) {
            return;
        }
        $this->nextAttemptAt = $now->modify(\sprintf('+%d seconds', self::INTERVAL_SECONDS));

        try {
            $this->lifecycle->tick();

            $this->keyValueStorage->set(
                self::HEARTBEAT_STORAGE_KEY,
                $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Webhook health tick failed', ['exception' => $e]);
        }
    }
}
