<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ShippingMethodLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Configuration for shipping method data loader.
 *
 * @phpstan-type ShippingMethodLoaderConfigData array{
 *   associations?: list<non-empty-string>,
 *   onlyAvailable?: bool
 * }
 *
 * @internal
 */
#[Package('discovery')]
final class ShippingMethodLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @codeCoverageIgnore
     *
     * @param list<non-empty-string> $associations Additional associations to load
     * @param bool $onlyAvailable Only return available shipping methods (default: true)
     */
    public function __construct(
        public readonly array $associations = [],
        public readonly bool $onlyAvailable = true,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDecorated(): AbstractContentDataLoaderConfig
    {
        throw new DecorationPatternException(self::class);
    }
}
