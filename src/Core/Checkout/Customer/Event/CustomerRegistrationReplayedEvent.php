<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a duplicate registration submission is answered by replaying the original
 * result. Carries the validated context token of the original registration. Deliberately not
 * flow- or mail-aware: those side effects already ran for the original request.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerRegistrationReplayedEvent extends Event
{
    public function __construct(
        public readonly string $contextToken,
        public readonly string $customerId,
    ) {
    }
}
