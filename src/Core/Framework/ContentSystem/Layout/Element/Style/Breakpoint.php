<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style;

use Shopware\Core\Framework\Log\Package;

/**
 * Canonical responsive breakpoint set for content-system style options. Fixed at the
 * framework level: every per-breakpoint style value is keyed by one of these cases.
 * Not plugin/app extensible.
 *
 * @internal
 */
#[Package('framework')]
enum Breakpoint: string
{
    case Xs = 'xs';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case Xxl = 'xxl';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $breakpoint): string => $breakpoint->value, self::cases());
    }
}
