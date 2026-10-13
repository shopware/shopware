<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Subscription;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum SubscriberType
{
    case App;
    case Admin;
    case User;
    case Integration;
    case AppIntegration;
    case None;
}
