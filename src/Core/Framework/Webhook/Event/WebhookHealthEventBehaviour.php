<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * Shared surface of the four endpoint-health lifecycle events: the owning-app ACL check,
 * the system context, and the scalar getters the flow/business-event encoder reads. The
 * using class declares `webhookId`, `appId`, and `fromState`.
 *
 * @internal
 */
trait WebhookHealthEventBehaviour
{
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        // Only the owning app may see its endpoint's health. One app must never see another
        // app's failures, and an app-less webhook's health is nobody's business event.
        return $this->appId !== null && $appId === $this->appId;
    }

    public function getContext(): Context
    {
        // Transitions happen on the delivery hot path, outside any request, so the system
        // context is the honest scope for an advisory event.
        return Context::createDefaultContext();
    }

    public function getWebhookId(): string
    {
        return $this->webhookId;
    }

    public function getFromState(): string
    {
        return $this->fromState->value;
    }
}
