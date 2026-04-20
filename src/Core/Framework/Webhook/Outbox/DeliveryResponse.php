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
        public string $requestContent,
        public ?int $processingTimeSeconds = null,
        public ?string $responseContent = null,
        public ?int $responseStatusCode = null,
        public ?string $responseReasonPhrase = null,
    ) {
    }

    public static function from(WebhookRequest $request, WebhookResult $result): self
    {
        return new self(
            requestContent: json_encode(['headers' => $request->headers, 'body' => $request->body], \JSON_THROW_ON_ERROR),
            processingTimeSeconds: $result->processingTimeSeconds,
            responseContent: $result->hasResponse()
                ? json_encode(['headers' => $result->headers, 'body' => $result->body], \JSON_THROW_ON_ERROR)
                : null,
            responseStatusCode: $result->statusCode,
            responseReasonPhrase: $result->reasonPhrase,
        );
    }

    /**
     * @return array{request_content: string, processing_time?: int, response_content?: string, response_status_code?: int, response_reason_phrase?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'request_content' => $this->requestContent,
            'processing_time' => $this->processingTimeSeconds,
            'response_content' => $this->responseContent,
            'response_status_code' => $this->responseStatusCode,
            'response_reason_phrase' => $this->responseReasonPhrase,
        ], static fn ($v) => $v !== null);
    }
}
