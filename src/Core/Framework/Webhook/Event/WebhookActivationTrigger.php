<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * What moved a webhook back to HEALTHY.
 *
 * @internal
 */
#[Package('framework')]
enum WebhookActivationTrigger: string
{
    case Trial = 'trial';

    case Idle = 'idle';

    case Manual = 'manual';

    case AppReset = 'app_reset';

    case AppReactivateApi = 'app_reactivate_api';
}
