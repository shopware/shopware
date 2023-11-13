<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
abstract class AbstractHttpCacheKeyGenerator
{
    /**
     * Generates a cache key for the given request.
     * This method should return a key that must only depend on a
     * normalized version of the request URI.
     * If the same URI can have more than one representation, based on some
     * headers, use a `vary` header to indicate them, and each representation will
     * be stored independently under the same cache key.
     *
     * @return string A key for the given request
     */
    abstract public function generate(Request $request): string;

    abstract public function getDecorated(): AbstractHttpCacheKeyGenerator;
}
