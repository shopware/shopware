<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @deprecated tag:v6.6.0 - Will be internal - reason:visibility-change
 */
#[Package('core')]
class WebhookEventMessage implements AsyncMessageInterface
{
    /**
     * @internal
     *
     * @param array<string, mixed> $payload
     **/
    public function __construct(
        private readonly string $webhookEventId,
        private readonly array $payload,
        private readonly ?string $appId,
        private readonly string $webhookId,
        private readonly string $shopwareVersion,
        private readonly string $url,
        private readonly ?string $secret,
        private readonly string $languageId,
        private readonly string $userLocale
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function getWebhookId(): string
    {
        return $this->webhookId;
    }

    public function getShopwareVersion(): string
    {
        return $this->shopwareVersion;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getWebhookEventId(): string
    {
        return $this->webhookEventId;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function getUserLocale(): ?string
    {
        return $this->userLocale;
    }
}
