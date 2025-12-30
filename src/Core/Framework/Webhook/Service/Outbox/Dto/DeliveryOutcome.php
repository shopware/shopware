<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox\Dto;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
readonly class DeliveryOutcome
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRY = 'retry';

    /**
     * @param array<string, mixed>|null $requestData
     * @param array<string, mixed>|null $responseData
     */
    public function __construct(
        public string $status,
        public int $processingTime,
        public ?array $requestData = null,
        public ?array $responseData = null,
        public ?int $responseStatusCode = null,
        public ?string $responseReasonPhrase = null,
        public ?\DateTimeImmutable $nextRetryAt = null,
    ) {
    }
}
