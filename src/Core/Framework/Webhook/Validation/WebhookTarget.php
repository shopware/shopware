<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WebhookTarget
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $ip,
    ) {
    }
}
