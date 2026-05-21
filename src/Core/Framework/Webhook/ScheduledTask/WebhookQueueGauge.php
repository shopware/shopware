<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WebhookQueueGauge
{
    public function __construct(
        public int $rows,
        public int $oldestAgeSeconds,
    ) {
    }
}
