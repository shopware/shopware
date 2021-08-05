<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class WebhookEventLogEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected ?string $appName;

    protected string $webhookName;

    protected string $eventName;

    protected string $deliveryStatus;

    protected ?int $timestamp;

    protected ?int $processingTime;

    protected ?string $appVersion;

    protected ?array $requestContent;

    protected ?array $responseContent;

    protected ?int $responseStatusCode;

    protected ?string $responseReasonPhrase;

    protected string $url;

    /**
     * @var string|object
     */
    protected $serializedWebhookMessage;

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

    public function getRequestContent(): ?array
    {
        return $this->requestContent;
    }

    public function setRequestContent(?array $requestContent): void
    {
        $this->requestContent = $requestContent;
    }

    public function getResponseContent(): ?array
    {
        return $this->responseContent;
    }

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
     * @return object|string
     */
    public function getSerializedWebhookMessage()
    {
        return $this->serializedWebhookMessage;
    }

    /**
     * @param string|object $serializedWebhookMessage
     */
    public function setSerializedWebhookMessage($serializedWebhookMessage): void
    {
        $this->serializedWebhookMessage = $serializedWebhookMessage;
    }
}
