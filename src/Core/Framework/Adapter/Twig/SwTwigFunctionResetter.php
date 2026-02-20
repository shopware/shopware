<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resets SwTwigFunction static caches between requests.
 *
 * This is essential for long runner environments (RoadRunner, FrankenPHP, Swoole)
 * where the same PHP process handles multiple requests. Without reset,
 * the escape filter cache in SwTwigFunction would grow unbounded,
 * causing memory leaks.
 *
 * @internal
 */
#[Package('framework')]
class SwTwigFunctionResetter implements ResetInterface
{
    public function reset(): void
    {
        SwTwigFunction::resetEscapeCache();
    }
}
