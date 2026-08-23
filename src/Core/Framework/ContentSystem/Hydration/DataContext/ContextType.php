<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ContextType: string
{
    case Single = 'single';
    case Collection = 'collection';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
