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
     * @return list<WebhookException>
     */
    public function getModificationViolations(array $webhookIds, Context $context): array
    {
        if ($context->getScope() === Context::SYSTEM_SCOPE) {
            return [];
        }

        $source = $context->getSource();
        $violations = [];

        foreach ($this->webhookLoader->getOwnership($webhookIds) as $ownership) {
            if ($ownership->belongsToApp()) {
                $violations[] = WebhookException::appWebhookNotModifiable($ownership->webhookId);

                continue;
            }

            if (!$source instanceof AdminApiSource || $source->isAdmin() || $ownership->isOwnedBy($source)) {
                continue;
            }

            $violations[] = WebhookException::webhookNotOwned($ownership->webhookId);
        }

        return $violations;
    }
}
