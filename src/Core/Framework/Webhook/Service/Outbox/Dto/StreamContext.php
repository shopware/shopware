<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox\Dto;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
readonly class StreamContext
{
    public function __construct(
        public string $partitionKey,
        public string $workerId,
    ) {
    }
}
