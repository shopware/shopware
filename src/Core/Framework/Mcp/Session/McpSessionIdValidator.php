<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Session;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
class McpSessionIdValidator
{
    /**
     * The MCP SDK transport parses the session header with Uuid::fromString(),
     * which throws on malformed input. Reject garbage early with a clean 400
     * instead of surfacing a 500 from the transport.
     */
    public function validate(Request $request): void
    {
        $sessionId = $request->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID);

        if ($sessionId !== null && !Uuid::isValid($sessionId)) {
            throw McpException::invalidSessionId();
        }
    }
}
