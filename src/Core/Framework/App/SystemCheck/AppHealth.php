<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\SystemCheck;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Status;

/**
 * @internal
 */
#[Package('framework')]
final readonly class AppHealth
{
    public function __construct(
        public string $name,
        public Status $status,
        public ?string $reason = null,
        public ?int $lastStatusCode = null,
        public ?string $lastAttemptAt = null,
        public ?string $lastAppActivityAt = null,
    ) {
    }
}
