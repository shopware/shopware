<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Runtime;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedInputRuntime
{
    private function __construct()
    {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $read
     *
     * @return TReturn
     */
    public static function access(string $removedIn, string $message, \Closure $read): mixed
    {
        Feature::triggerDeprecationOrThrow($removedIn, $message);

        return $read();
    }
}
