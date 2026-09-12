<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Cors;

use Shopware\Core\Framework\Log\Package;

/**
 * Contributes header names to the CORS headers that the API answers preflight requests with.
 *
 * Implementations are collected via the `shopware.api.cors_header_provider` tag, which is applied
 * automatically to autoconfigured services. Because preflight requests are answered before routing,
 * the contributed headers are global: they are not scoped to the routes that actually read them.
 */
#[Package('framework')]
interface CorsHeaderProviderInterface
{
    public const SERVICE_TAG = 'shopware.api.cors_header_provider';

    /**
     * Providers run in the order of their tag priority and share one `CorsHeaders` instance, so a
     * provider can also remove a header name that an earlier provider contributed. Shopware's own
     * names come from `CoreCorsHeaderProvider`, which runs first.
     */
    public function provide(CorsHeaders $headers): void;
}
