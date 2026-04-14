<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\Service\WebhookResult;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class DeliveryResponse
{
    public function __construct(
        public int $processingTime,
        public ?string $requestContent = null,
        public ?string $responseContent = null,
        public ?int $responseStatusCode = null,
        public ?string $responseReasonPhrase = null,
    ) {
    }

    public static function from(WebhookRequest $request, WebhookResult $result, int $processingTime): self
    {
        return new self(
            processingTime: $processingTime,
            requestContent: json_encode(['headers' => $request->headers, 'body' => $request->body], \JSON_THROW_ON_ERROR),
            responseContent: $result->hasResponse()
                ? json_encode(['headers' => $result->headers, 'body' => $result->body], \JSON_THROW_ON_ERROR)
                : null,
            responseStatusCode: $result->statusCode,
            responseReasonPhrase: $result->reasonPhrase,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'processing_time' => $this->processingTime,
            'request_content' => $this->requestContent,
            'response_content' => $this->responseContent,
            'response_status_code' => $this->responseStatusCode,
            'response_reason_phrase' => $this->responseReasonPhrase,
        ], static fn ($v) => $v !== null);
    }
}
