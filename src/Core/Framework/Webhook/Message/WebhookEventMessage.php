<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

class WebhookEventMessage
{
    private array $payload;

    private ?string $appId;

    private string $webhookId;

    private string $url;

    private string $shopwareVersion;

    private string $webhookEventId;

    public function __construct(string $webhookEventId, array $payload, ?string $appId, string $webhookId, string $shopwareVersion, string $url)
    {
        $this->webhookEventId = $webhookEventId;
        $this->payload = $payload;
        $this->appId = $appId;
        $this->webhookId = $webhookId;
        $this->shopwareVersion = $shopwareVersion;
        $this->url = $url;
    }

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
}
