<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class DataRequirement
{
    /**
     * @codeCoverageIgnore
     */
    public function __construct(
        public string $key,
        public string $source,
        public AbstractContentDataLoaderConfig $config
    ) {
    }
}
