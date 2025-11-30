<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
interface DistributionConfig
{
    public function getStrategy(): DistributionStrategy;

    /**
     * Consumer property name override. Null = use provider's context key.
     */
    public function getConsumerAlias(): ?string;
}
