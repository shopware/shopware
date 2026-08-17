<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * The VAT ID format pattern configured for a single country in Settings > Countries.
 *
 * @internal
 */
#[Package('checkout')]
final class VatIdPattern
{
    public function __construct(
        public readonly string $iso,
        public readonly string $pattern,
    ) {
    }

    public function matches(string $vatId): bool
    {
        return preg_match($this->toRegex(), $vatId) === 1;
    }

    /**
     * Merchants can edit the pattern, so it is not guaranteed to compile.
     */
    public function isValid(): bool
    {
        return @preg_match($this->toRegex(), '') !== false;
    }

    private function toRegex(): string
    {
        return '/^' . $this->pattern . '$/';
    }
}
