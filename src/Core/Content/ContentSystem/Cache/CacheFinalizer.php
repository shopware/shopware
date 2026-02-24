<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Cache;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CacheFinalizer
{
    public function __construct(
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function finalize(Request $request, RenderingCacheContext $cacheContext): void
    {
        if ($cacheContext->isDisabled()) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);

            return;
        }

        $this->cacheTagCollector->addTag(...$cacheContext->getTags());
    }
}
