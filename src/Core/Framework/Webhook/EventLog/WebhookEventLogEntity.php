<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class WebhookEventLogEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected ?string $appName = null;

    protected string $webhookName;

    protected string $eventName;

    protected string $deliveryStatus;

    protected ?int $timestamp = null;

    protected ?int $processingTime = null;

    protected ?string $appVersion = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $requestContent;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $responseContent;

    protected ?int $responseStatusCode = null;

    protected ?string $responseReasonPhrase = null;

    protected string $url;

    /**
     * @internal
     */
    protected string|object $serializedWebhookMessage;

    public function getAppName(): ?string
    {
        return $this->appName;
    }

    public function setAppName(?string $appName): void
    {
        $this->appName = $appName;
    }

    public function getWebhookName(): string
    {
        return $this->webhookName;
    }

    public function setWebhookName(string $webhookName): void
    {
        $this->webhookName = $webhookName;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getDeliveryStatus(): string
    {
        return $this->deliveryStatus;
    }

    public function setDeliveryStatus(string $deliveryStatus): void
    {
        $this->deliveryStatus = $deliveryStatus;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    public function setTimestamp(?int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getProcessingTime(): ?int
    {
        return $this->processingTime;
    }

    public function setProcessingTime(?int $processingTime): void
    {
        $this->processingTime = $processingTime;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $appVersion): void
    {
        $this->appVersion = $appVersion;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestContent(): ?array
    {
        return $this->requestContent;
    }

    /**
     * @param array<string, mixed>|null $requestContent
     */
    public function setRequestContent(?array $requestContent): void
    {
        $this->requestContent = $requestContent;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseContent(): ?array
    {
        return $this->responseContent;
    }

    /**
     * @param array<string, mixed>|null $responseContent
     */
    public function setResponseContent(?array $responseContent): void
    {
        $this->responseContent = $responseContent;
    }

    public function getResponseStatusCode(): ?int
    {
        return $this->responseStatusCode;
    }

    public function setResponseStatusCode(?int $responseStatusCode): void
    {
        $this->responseStatusCode = $responseStatusCode;
    }

    public function getResponseReasonPhrase(): ?string
    {
        return $this->responseReasonPhrase;
    }

    public function setResponseReasonPhrase(?string $responseReasonPhrase): void
    {
        $this->responseReasonPhrase = $responseReasonPhrase;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @internal
     */
    public function getSerializedWebhookMessage(): object|string
    {
        $this->checkIfPropertyAccessIsAllowed('serializedWebhookMessage');

        return $this->serializedWebhookMessage;
    }

    /**
     * @internal
     */
    public function setSerializedWebhookMessage(object|string $serializedWebhookMessage): void
    {
        $this->serializedWebhookMessage = $serializedWebhookMessage;
    }
}
