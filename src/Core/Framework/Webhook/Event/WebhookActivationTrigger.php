<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * What moved a webhook back to HEALTHY — carried on {@see WebhookActivatedEvent}.
 *
 * @internal
 */
#[Package('framework')]
enum WebhookActivationTrigger: string
{
    /**
     * A released trial delivery returned 2xx (the half-open ladder).
     */
    case Trial = 'trial';

    /**
     * Idle promotion: DEGRADED with nothing held and nothing in flight.
     */
    case Idle = 'idle';

    /**
     * Manual operator reactivation (admin `PATCH active = true`).
     */
    case Manual = 'manual';

    /**
     * App install/update clean-slate reset.
     */
    case AppReset = 'app_reset';

    /**
     * The app's self-service reactivate API.
     */
    case AppReactivateApi = 'app_reactivate_api';
}
