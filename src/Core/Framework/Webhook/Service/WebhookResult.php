<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WebhookResult
{
    /**
     * @param array<string, string[]>|null $headers
     */
    public function __construct(
        public mixed $body,
        public ?int $statusCode,
        public ?string $reasonPhrase,
        public ?array $headers,
        public ?string $errorMessage = null,
        public ?\Throwable $exception = null,
        public ?int $processingTimeSeconds = null,
    ) {
    }

    public function hasResponse(): bool
    {
        return $this->statusCode !== null;
    }

    /**
     * @phpstan-assert-if-true null $this->exception
     */
    public function successful(): bool
    {
        return $this->exception === null;
    }
}
