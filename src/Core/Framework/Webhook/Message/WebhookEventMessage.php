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

    private ?string $secret;

    /**
     * @depretacted tag:v6.5.0 - This will be required in the future
     **/
    private ?string $languageId;

    /**
     * @depretacted tag:v6.5.0 - This will be required in the future
     **/
    private ?string $userLocale;

    /**
     * @depretacted tag:v6.5.0 - Parameters $languageId and $userLocale will be required
     **/
    public function __construct(
        string $webhookEventId,
        array $payload,
        ?string $appId,
        string $webhookId,
        string $shopwareVersion,
        string $url,
        ?string $secret = null,
        ?string $languageId = null,
        ?string $userLocale = null
    ) {
        $this->webhookEventId = $webhookEventId;
        $this->payload = $payload;
        $this->appId = $appId;
        $this->webhookId = $webhookId;
        $this->shopwareVersion = $shopwareVersion;
        $this->url = $url;
        $this->secret = $secret;
        $this->languageId = $languageId;
        $this->userLocale = $userLocale;
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
