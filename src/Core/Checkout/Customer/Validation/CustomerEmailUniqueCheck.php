<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple struct with public readonly properties.
 */
#[Package('checkout')]
final class CustomerEmailUniqueCheck
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $customerId = null,
        public readonly ?string $boundSalesChannelId = null,
        public readonly bool $guest = false,
    ) {
    }
}
