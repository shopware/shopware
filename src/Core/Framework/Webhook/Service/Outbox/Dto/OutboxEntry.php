<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox\Dto;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

#[Package('framework')]
readonly class OutboxEntry
{
    public function __construct(
        public string $id,
        public WebhookEventMessage $message,
        public int $executionCount,
        public ?\DateTimeImmutable $nextRetryAt,
    ) {
    }
}
