<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Who disabled a webhook. Automation never undoes a human's deliberate kill.
 * Operator-disabled webhooks are excluded from every automatic recovery path (the
 * app-update reset and the app's self-service API). Only the operator gesture
 * (admin `PATCH active = true`) brings them back. Escalation-disabled webhooks
 * recover via the app install/update reset or manual reactivation.
 *
 * @internal
 */
#[Package('framework')]
enum DisabledOrigin: string
{
    case Operator = 'operator';
    case Escalation = 'escalation';
}
