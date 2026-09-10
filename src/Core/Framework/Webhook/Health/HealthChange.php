<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * The row before and after one health transition.
 *
 * @internal
 */
#[Package('framework')]
final readonly class HealthChange
{
    public function __construct(
        public HealthRow $from,
        public HealthRow $to,
    ) {
    }

    public function changedState(): bool
    {
        return $this->from->state !== $this->to->state;
    }
}
