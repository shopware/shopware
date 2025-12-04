<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Cache;

use Shopware\Core\Content\ContentSystem\RenderingCacheContext;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Finalizes HTTP cache state for content routes after hydration.
 *
 * @internal
 */
#[Package('discovery')]
class CacheFinalizer
{
    public function __construct(
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    /**
     * Finalizes cache state after hydration.
     *
     * If cache is disabled (uncacheable data loaded), disables HTTP cache.
     * Otherwise, adds accumulated hydration tags to the response.
     */
    public function finalize(Request $request, RenderingCacheContext $cacheContext): void
    {
        if ($cacheContext->isDisabled()) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);

            return;
        }

        $this->cacheTagCollector->addTag(...$cacheContext->getTags());
    }
}
