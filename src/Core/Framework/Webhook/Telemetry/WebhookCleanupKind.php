<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum WebhookCleanupKind: string
{
    case SUCCESS_FAILED = 'success_failed';
    case QUEUED_NO_DELIVERY = 'queued_no_delivery';
    case ORPHANED_STREAMS = 'orphaned_streams';
}
