<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum WebhookDeliveryStatus: string
{
    case QUEUED = 'queued';
    case PENDING_RETRY = 'pending_retry';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::SUCCESS || $this === self::FAILED;
    }
}
