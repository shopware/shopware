<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class WebhookEventMessage
{
    public const DEFAULT_PARTITION_KEY = 'default';

    /**
     * @internal
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $webhookHeaders
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
        private readonly string $userLocale,
        private readonly array $webhookHeaders = [],
        /**
         * @deprecated tag:v6.8.0 - Will become non-nullable. Null only for BC with old serialized messages already in the queue.
         */
        public readonly ?string $partitionKey = null,
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

    /**
     * @return array<string, string>
     */
    public function getWebhookHeaders(): array
    {
        return $this->webhookHeaders;
    }

    /**
     * Returns the raw partition key input (e.g. app ID or 'default').
     */
    public function getPartitionKey(): string
    {
        return $this->partitionKey ?? $this->appId ?? self::DEFAULT_PARTITION_KEY;
    }

    /**
     * Distinguishes new transport messages from legacy serialized messages.
     *
     * @deprecated tag:v6.9.0 - Will be removed when all messages in the queue have been processed that were serialized without an explicit partition key.
     *
     * @phpstan-ignore shopware.deprecatedMethod (called on every dispatch during the rollout window; deprecation notice would pollute logs)
     */
    public function isReworkEnvelope(): bool
    {
        return isset($this->partitionKey);
    }
}
