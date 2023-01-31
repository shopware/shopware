<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('core')]
class WebhookEventMessage implements AsyncMessageInterface
{
    /**
     * @internal
     * @depretacted tag:v6.5.0 - Parameters $languageId and $userLocale will be required
     **/
    public function __construct(
        private readonly string $webhookEventId,
        private readonly array $payload,
        private readonly ?string $appId,
        private readonly string $webhookId,
        private readonly string $shopwareVersion,
        private readonly string $url,
        private readonly ?string $secret = null,
        /**
         * @depretacted tag:v6.5.0 - This will be required in the future
         **/
        private readonly ?string $languageId = null,
        /**
         * @depretacted tag:v6.5.0 - This will be required in the future
         **/
        private readonly ?string $userLocale = null
    ) {
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
