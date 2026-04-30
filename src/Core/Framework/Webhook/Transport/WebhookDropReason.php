<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Transport;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum WebhookDropReason: string
{
    case UNSERIALIZE_ERROR = 'unserialize_error';
    case CLASS_MISMATCH = 'class_mismatch';
    case EVENT_ID_MISMATCH = 'event_id_mismatch';
}
