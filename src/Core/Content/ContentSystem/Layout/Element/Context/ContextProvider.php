<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\DistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ContextProvider
{
    public function __construct(
        public readonly ContextType $type,
        public readonly DistributionConfig $config
    ) {
    }

    public function getDistribution(): DistributionConfig
    {
        return $this->config;
    }
}
