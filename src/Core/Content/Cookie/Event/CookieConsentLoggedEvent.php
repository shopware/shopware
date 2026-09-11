<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Event;

use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a visitor's cookie consent decision was stored.
 *
 * Deliberately a plain PHP event and not hookable: it fires on the highest-volume
 * anonymous endpoint of the shop, fanning that out to webhooks needs an explicit opt-in.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
class CookieConsentLoggedEvent extends Event
{
    public function __construct(public readonly CookieConsentRecord $record)
    {
    }
}
