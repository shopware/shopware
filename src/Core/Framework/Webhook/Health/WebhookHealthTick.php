<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;

/**
 * @internal
 */
#[Package('framework')]
class WebhookHealthTick
{
    public const INTERVAL_SECONDS = 60;

    /**
     * Last completed tick, in {@see Defaults::STORAGE_DATE_TIME_FORMAT}.
     */
    public const HEARTBEAT_STORAGE_KEY = 'webhook.health.tick.completed_at';

    private ?\DateTimeImmutable $nextAttemptAt = null;

    public function __construct(
        private readonly AbstractKeyValueStorage $keyValueStorage,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly WebhookHealthService $healthService,
    ) {
    }

    public function run(): void
    {
        $now = $this->clock->now();
        if ($this->nextAttemptAt !== null && $now < $this->nextAttemptAt) {
            return;
        }

        $this->nextAttemptAt = $now->modify(\sprintf('+%d seconds', self::INTERVAL_SECONDS));

        try {
            $this->healthService->tick();

            $this->keyValueStorage->set(
                self::HEARTBEAT_STORAGE_KEY,
                $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            );
        } catch (\Throwable $e) {
            // Health maintenance must not stop the delivery worker.
            $this->logger->error('Webhook health tick failed', ['exception' => $e]);
        }
    }
}
