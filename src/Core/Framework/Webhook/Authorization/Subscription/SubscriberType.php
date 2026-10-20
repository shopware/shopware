<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Subscription;

use Shopware\Core\Framework\Api\Acl\ApiCallerType;
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
    case User;
    case Integration;
    case None;

    public static function of(ApiCallerType $caller): self
    {
        return match ($caller) {
            ApiCallerType::User => self::User,
            ApiCallerType::Integration => self::Integration,
        };
    }
}
