<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Shopware\Core\Framework\Log\Package;

/**
 * Cumulative stuck-row counts: a row stuck for 24h is also counted in the 1h and 15m
 * fields.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WebhookStuckInflightCounts
{
    public function __construct(
        public int $fifteenMinutes,
        public int $oneHour,
        public int $twentyFourHours,
    ) {
    }
}
