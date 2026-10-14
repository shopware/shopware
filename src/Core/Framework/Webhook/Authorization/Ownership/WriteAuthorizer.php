<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Ownership;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[Package('framework')]
class WriteAuthorizer
{
    public function __construct(private readonly WebhookLoader $webhookLoader)
    {
    }

    /**
     * @param list<string> $webhookIds
     *
     * @return array<string, WebhookException> keyed by webhook id
     */
    public function getModificationViolations(array $webhookIds, Context $context): array
    {
        if ($context->getScope() === Context::SYSTEM_SCOPE) {
            return [];
        }

        $source = $context->getSource();
        $violations = [];

        $subscriptions = $this->webhookLoader->getSubscriptions($webhookIds);

        foreach ($webhookIds as $webhookId) {
            $subscription = $subscriptions[$webhookId] ?? null;
            if ($subscription?->belongsToApp()) {
                $violations[$webhookId] = WebhookException::appWebhookNotModifiable($webhookId);

                continue;
            }

            // a webhook without an app or owner is not loaded, so only an admin may change it
            if (!$source instanceof AdminApiSource || $source->isAdmin() || $subscription?->isOwnedBy($source)) {
                continue;
            }

            $violations[$webhookId] = WebhookException::webhookNotOwned($webhookId);
        }

        return $violations;
    }
}
