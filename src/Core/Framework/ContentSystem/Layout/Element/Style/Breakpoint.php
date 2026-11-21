<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style;

use Shopware\Core\Framework\Log\Package;

/**
 * Defines the framework's fixed breakpoints and inheritance rules for responsive fields.
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
     * Exposes the Storefront's fallback order to the Admin through the element-types API.
     *
     * @return array<string, list<string>>
     */
    public static function fallbackMap(): array
    {
        $map = [];
        foreach (self::cases() as $breakpoint) {
            $map[$breakpoint->value] = array_map(static fn (self $fallback): string => $fallback->value, $breakpoint->fallbacks());
        }

        return $map;
    }

    /**
     * Resolves one field by checking its own value first, then its fallbacks in order.
     * Missing keys and null values fall through. Zero and false count as set.
     *
     * @template T
     *
     * @param array<string, T> $values
     *
     * @return T|null
     */
    public function resolve(array $values): mixed
    {
        if (isset($values[$this->value])) {
            return $values[$this->value];
        }

        foreach ($this->fallbacks() as $fallback) {
            if (isset($values[$fallback->value])) {
                return $values[$fallback->value];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $breakpoint): string => $breakpoint->value, self::cases());
    }

    /**
     * Lists the full fallback order for this breakpoint.
     * Each candidate contributes only its own value.
     *
     * @return list<self>
     */
    private function fallbacks(): array
    {
        return match ($this) {
            self::Xs => [self::Sm, self::Lg],
            self::Sm, self::Md, self::Xl => [self::Lg],
            self::Xxl => [self::Xl, self::Lg],
            self::Lg => [],
        };
    }
}
