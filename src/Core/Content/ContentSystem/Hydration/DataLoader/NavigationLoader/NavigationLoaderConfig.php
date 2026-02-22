<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Configuration for navigation data loader.
 *
 * @phpstan-type NavigationLoaderConfigData array{
 *   rootId?: non-empty-string,
 *   depth?: positive-int,
 *   activeProperty?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('discovery')]
final class NavigationLoaderConfig extends AbstractContentDataLoaderConfig
{
    public const DEFAULT_DEPTH = 2;

    /**
     * @codeCoverageIgnore
     *
     * @param non-empty-string|null $rootId Navigation root ID or alias (main-navigation, service-navigation, footer-navigation)
     * @param positive-int $depth Navigation tree depth
     * @param non-empty-string $activeProperty Element property name to read active category ID from
     */
    public function __construct(
        public readonly ?string $rootId = null,
        public readonly int $depth = self::DEFAULT_DEPTH,
        public readonly string $activeProperty = 'activeId',
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
