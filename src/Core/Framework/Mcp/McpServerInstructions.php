<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Schema\Request\DiscoverRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * The server instructions of both MCP endpoints, per protocol era. packages/mcp.php configures the handshake
 * texts. The SDK answers `server/discover` of the stateless era (2026-07-28) from the same configuration, so
 * the controllers swap a shipped handshake text for its stateless twin there: the stateless era does not offer
 * the toolset session tools the handshake text points to. Instructions a project configured itself are left alone.
 */
#[Package('framework')]
final class McpServerInstructions
{
    public const ADMIN = "This MCP server exposes Shopware e-commerce platform capabilities.\n"
        . "Use entity tools to search, read, and manage shop data.\n"
        . 'The advertised tool list is not the full catalogue. If no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported; '
        . "use shopware-toolsets-list and shopware-toolset-enable to make a matched tool callable if your client cannot invoke it inline.\n"
        . "All operations respect the authenticated user's ACL permissions.\n";

    public const ADMIN_STATELESS = "This MCP server exposes Shopware e-commerce platform capabilities.\n"
        . "Use entity tools to search, read, and manage shop data.\n"
        . 'The advertised tool list is not the full catalogue. If no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported, '
        . 'then call the matched tool by name. If your client only calls advertised tools, reconnect with the toolset of the match in the endpoint URL, '
        . "for example ?toolsets=order,media.\n"
        . "All operations respect the authenticated user's ACL permissions.\n";

    public const STORE_API = 'This MCP server exposes Store API capabilities. All operations run in the current sales-channel context and use Store API authentication headers. '
        . 'The advertised tool list is not the full catalogue: if no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported, '
        . 'then use shopware-toolsets-list and shopware-toolset-enable to make a matched tool callable if your client cannot invoke it inline.';

    public const STORE_API_STATELESS = 'This MCP server exposes Store API capabilities. All operations run in the current sales-channel context and use Store API authentication headers. '
        . 'The advertised tool list is not the full catalogue: if no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported, '
        . 'then call the matched tool by name. If your client only calls advertised tools, reconnect with the toolset of the match in the endpoint URL, for example ?toolsets=store-api.';

    private const STATELESS_TWINS = [
        self::ADMIN => self::ADMIN_STATELESS,
        self::STORE_API => self::STORE_API_STATELESS,
    ];

    /**
     * Rewrites the instructions of a `server/discover` answer on the stateless era. Every other answer is
     * returned unchanged.
     */
    public static function applyToStatelessDiscover(Request $request, Response $response): void
    {
        if ($request->getMethod() !== Request::METHOD_POST || $response instanceof StreamedResponse) {
            return;
        }

        if (!McpProtocolEra::isStateless($request) || (self::decode($request->getContent())->method ?? null) !== DiscoverRequest::getMethod()) {
            return;
        }

        $content = $response->getContent();
        $body = \is_string($content) ? self::decode($content) : null;
        $result = $body->result ?? null;

        if (!$result instanceof \stdClass || !\is_string($result->instructions ?? null)) {
            return;
        }

        $twin = self::STATELESS_TWINS[$result->instructions] ?? null;
        if ($twin === null) {
            return;
        }

        $result->instructions = $twin;

        // Decoded to objects and encoded back as such, so empty JSON objects such as `"tools": {}` stay objects.
        $content = json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $response->setContent($content);

        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) \strlen($content));
        }
    }

    private static function decode(string $json): ?\stdClass
    {
        try {
            $decoded = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return $decoded instanceof \stdClass ? $decoded : null;
    }
}
