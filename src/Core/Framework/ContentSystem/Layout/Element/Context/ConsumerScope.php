<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\Log\Package;

/**
 * Where a {@see ContextConsumer} takes its value from: `Parent` matches the context an ancestor provides,
 * `Root` matches the layout's root-ambient context. `Parent` is the default and the only scope a
 * `redistribute` chain carries.
 */
#[Package('framework')]
enum ConsumerScope: string
{
    case Parent = 'parent';
    case Root = 'root';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
