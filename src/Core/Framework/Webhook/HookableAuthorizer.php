<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

/**
 * Custom recipient-authorization strategy for hookable events.
 *
 * Authorizers are registered with the 'shopware.webhook.authorizer' tag. For each dispatched event
 * the WebhookManager uses the first authorizer that supports() it to decide which recipients may
 * receive it; if no authorizer supports the event, the default Hookable::isAllowed() check applies.
 */
#[Package('framework')]
interface HookableAuthorizer
{
    public function supports(Hookable $event): bool;

    public function isAllowed(Hookable $event, string $appId, AclPrivilegeCollection $permissions): bool;
}
