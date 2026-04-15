<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
readonly class HookableEventDescription
{
    /**
     * @param list<string> $privileges
     */
    public function __construct(
        public string $eventName,
        public string $description,
        public array $privileges,
    ) {
    }
}
