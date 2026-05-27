<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Runs a callback within a temporary Twig timezone override.
 *
 * @internal
 */
#[Package('framework')]
final class TwigTimezoneOverride
{
    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public static function run(Environment $twig, \DateTimeZone|string|null $timezone, callable $fn): mixed
    {
        if ($timezone === null || $timezone === '' || !$twig->hasExtension(CoreExtension::class)) {
            return $fn();
        }

        /** @var CoreExtension $coreExtension */
        $coreExtension = $twig->getExtension(CoreExtension::class);
        $previous = $coreExtension->getTimezone();
        $coreExtension->setTimezone($timezone);

        try {
            return $fn();
        } finally {
            $coreExtension->setTimezone($previous);
        }
    }
}
