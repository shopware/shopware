<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Runtime;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * Resets CachedEscaperRuntime static caches between requests.
 *
 * This is essential for long runner environments (RoadRunner, FrankenPHP, Swoole)
 * where the same PHP process handles multiple requests. Without reset,
 * the escape filter cache in CachedEscaperRuntime would grow unbounded,
 * causing memory leaks.
 */
#[Package('framework')]
final class CachedEscaperRuntimeResetter implements ResetInterface
{
    public function reset(): void
    {
        CachedEscaperRuntime::resetEscapeCache();
    }
}
