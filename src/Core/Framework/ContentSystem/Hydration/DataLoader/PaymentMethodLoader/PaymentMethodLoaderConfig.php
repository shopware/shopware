<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\PaymentMethodLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * Configuration for payment method data loader.
 *
 * @phpstan-type PaymentMethodLoaderConfigData array{
 *   associations?: list<non-empty-string>,
 *   onlyAvailable?: bool
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentMethodLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param list<non-empty-string> $associations Additional associations to load
     * @param bool $onlyAvailable Only return available payment methods (default: true)
     */
    public function __construct(
        public array $associations = [],
        public bool $onlyAvailable = true,
    ) {
    }
}
