<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[Package('framework')]
class WebhookSendException extends WebhookException
{
    /**
     * @param array<string, string[]>|null $responseHeaders
     */
    public function __construct(
        int $statusCode,
        string $errorCode,
        string $message,
        array $parameters = [],
        ?\Throwable $previous = null,
        private readonly ?int $responseStatusCode = null,
        private readonly ?string $responseReasonPhrase = null,
        private readonly ?array $responseHeaders = null,
        private readonly mixed $responseBody = null,
    ) {
        parent::__construct($statusCode, $errorCode, $message, $parameters, $previous);
    }

    public function hasResponse(): bool
    {
        return $this->responseStatusCode !== null;
    }

    public function getResponseStatusCode(): ?int
    {
        return $this->responseStatusCode;
    }

    public function getResponseReasonPhrase(): ?string
    {
        return $this->responseReasonPhrase;
    }

    /**
     * @return array<string, string[]>|null
     */
    public function getResponseHeaders(): ?array
    {
        return $this->responseHeaders;
    }

    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }
}
