<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

/**
 * Decides which recipients may receive an event, in place of the event's own privilege check.
 *
 * Authorizers are registered with the 'shopware.webhook.authorizer' tag. For each dispatched event
 * the WebhookManager uses the first authorizer that supports() it; if no authorizer supports the
 * event, the default Hookable::isAllowed() check applies.
 *
 * This exists because Hookable::isAllowed() only sees the app id and its privileges, which is not
 * enough to restrict an event to a class of recipient, for example to services only. An authorizer
 * receives the whole Webhook, so it can decide on the recipient's source type, url or version.
 *
 * @internal
 */
#[Package('framework')]
interface HookableAuthorizer
{
    public function supports(Hookable $event): bool;

    public function isAllowed(Hookable $event, Webhook $webhook, AclPrivilegeCollection $permissions): bool;
}
