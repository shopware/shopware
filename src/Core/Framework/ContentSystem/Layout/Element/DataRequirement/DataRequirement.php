<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class DataRequirement
{
    public function __construct(
        public string $key,
        public string $source,
        public AbstractContentDataLoaderConfig $config
    ) {
    }
}
