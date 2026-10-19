<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
trait WebhookHealthEventBehaviour
{
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $this->appId !== null && $appId === $this->appId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        $payload = [];
        foreach (array_keys(self::getAvailableData()->toArray()) as $key) {
            $getter = 'get' . $key;
            $payload[$key] = $this->$getter(); /* @phpstan-ignore-line */
        }

        return $payload;
    }

    public function getContext(): Context
    {
        // Health transitions have no request context.
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

    public function getWebhookName(): ?string
    {
        return $this->webhookName;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt->format(\DateTimeInterface::ATOM);
    }
}
