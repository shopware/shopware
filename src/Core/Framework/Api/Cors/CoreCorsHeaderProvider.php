<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Cors;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

/**
 * @internal
 */
#[Package('framework')]
final class CoreCorsHeaderProvider implements CorsHeaderProviderInterface
{
    /**
     * The MCP headers must be part of the global lists because preflight requests are answered
     * before routing, so the target route (e.g. the MCP endpoints) is unknown at that point.
     */
    private const DEFAULT_HEADERS = [
        'Content-Type',
        'Authorization',
        PlatformRequest::HEADER_CONTEXT_TOKEN,
        PlatformRequest::HEADER_ACCESS_KEY,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_INHERITANCE,
        PlatformRequest::HEADER_INDEXING_BEHAVIOR,
        PlatformRequest::HEADER_INCLUDE_SEO_URLS,
        PlatformRequest::HEADER_MCP_SESSION_ID,
        PlatformRequest::HEADER_MCP_PROTOCOL_VERSION,
    ];

    public function provide(CorsHeaders $headers): void
    {
        $headers->addAllowed(...self::DEFAULT_HEADERS);
        $headers->addExposed(...self::DEFAULT_HEADERS);
    }
}
