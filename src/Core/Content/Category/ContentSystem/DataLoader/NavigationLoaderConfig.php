<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

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
final readonly class NavigationLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $rootId Navigation root ID or alias (main-navigation, service-navigation, footer-navigation)
     * @param positive-int|null $depth Navigation tree depth, null to follow the sales channel's navigationCategoryDepth
     * @param non-empty-string $activeProperty Element property name to read active category ID from
     */
    public function __construct(
        public ?string $rootId = null,
        public ?int $depth = null,
        public string $activeProperty = 'activeId',
    ) {
    }

    /**
     * @return NavigationLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->rootId !== null) {
            $data['rootId'] = $this->rootId;
        }

        if ($this->depth !== null) {
            $data['depth'] = $this->depth;
        }

        if ($this->activeProperty !== 'activeId') {
            $data['activeProperty'] = $this->activeProperty;
        }

        return $data;
    }
}
