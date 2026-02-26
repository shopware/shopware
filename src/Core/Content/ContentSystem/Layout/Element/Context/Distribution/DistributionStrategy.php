<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

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

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
