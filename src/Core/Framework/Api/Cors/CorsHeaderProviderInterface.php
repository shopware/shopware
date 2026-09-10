<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Cors;

use Shopware\Core\Framework\Log\Package;

/**
 * Contributes additional header names to the CORS headers that the API answers preflight requests with.
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
     * Request headers a cross-origin client is allowed to send (`Access-Control-Allow-Headers`).
     *
     * @return list<string>
     */
    public function getAllowedHeaders(): array;

    /**
     * Response headers a cross-origin client is allowed to read (`Access-Control-Expose-Headers`).
     *
     * @return list<string>
     */
    public function getExposedHeaders(): array;
}
