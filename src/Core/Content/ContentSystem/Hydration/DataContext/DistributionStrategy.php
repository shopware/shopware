<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
enum DistributionStrategy: string
{
    case Broadcast = 'broadcast';
    case Indexed = 'indexed';
    case Keyed = 'keyed';
    case Sliced = 'sliced';
    case Iterator = 'iterator';
}
