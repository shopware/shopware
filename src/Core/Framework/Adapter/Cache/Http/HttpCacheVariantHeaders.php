<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

/**
 * Single source of truth for the request inputs that select a cache variant of a URL.
 *
 * Every name listed here is emitted as `Vary` on sales channel responses and folded into
 * the internal HTTP cache key, so the internal cache and external reverse proxies always
 * differentiate the same variants.
 *
 * @internal
 */
#[Package('framework')]
final class HttpCacheVariantHeaders
{
    /**
     * `sw-cache-hash` is transported as a cookie/header; the other values are plain request headers.
     */
    public const HEADERS = [
        PlatformRequest::HEADER_ACCESS_KEY,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_CURRENCY_ID,
        HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
    ];
}
