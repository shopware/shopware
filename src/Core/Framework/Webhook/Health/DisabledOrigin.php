<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Automation must not reactivate operator-disabled webhooks.
 *
 * @internal
 */
#[Package('framework')]
enum DisabledOrigin: string
{
    case Operator = 'operator';
    case Escalation = 'escalation';
}
