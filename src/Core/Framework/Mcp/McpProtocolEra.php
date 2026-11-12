<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Server\Wire\InboundClassifier;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Tells whether an MCP request is on the stateless era (2026-07-28), by the same rule the SDK routes it
 * with ({@see InboundClassifier}): a request that claims its revision in `params._meta` is stateless,
 * everything else is on the handshake era. See adr/2026-09-24-mcp-stateless-era-degradation-policy.md.
 */
#[Package('framework')]
final class McpProtocolEra
{
    /**
     * The toolset session tools. The stateless era has no session to keep an enabled toolset in, so they
     * are not advertised there and answer a direct call with an error. Toolsets are chosen with
     * `?toolsets=` on that era, and `shopware-tool-search` names the toolset of every match.
     */
    public const HIDDEN_ON_STATELESS_ERA = [
        McpToolsetRegistry::LIST_TOOLSETS_TOOL,
        McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
    ];

    /**
     * Several handlers ask during one request, so the answer is kept on the request.
     */
    private const ATTRIBUTE = 'mcp._stateless_era';

    public static function isStateless(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        $known = $request->attributes->get(self::ATTRIBUTE);
        if (\is_bool($known)) {
            return $known;
        }

        $header = $request->headers->get(InboundClassifier::PROTOCOL_VERSION_HEADER);
        $headers = $header !== null ? [InboundClassifier::PROTOCOL_VERSION_HEADER => $header] : [];

        $stateless = (new InboundClassifier())->classify($request->getMethod(), $request->getContent(), $headers)->modern;
        $request->attributes->set(self::ATTRIBUTE, $stateless);

        return $stateless;
    }

    /**
     * The `isError` text of a toolset session tool called directly on the stateless era, for clients
     * that kept the tool name from an earlier handshake connection.
     */
    public static function toolsetToolUnavailableMessage(string $tool, string $toolset = '<toolset>'): string
    {
        return \sprintf(
            '%s is not available on protocol revision 2026-07-28, which has no MCP session to keep toolsets in. '
            . 'Call %s to find a tool and call it by name with tools/call; it does not need to be advertised in tools/list. '
            . 'If your MCP client only calls advertised tools, reconnect to the MCP endpoint URL with the toolset in the query, for example "?%s=%s". '
            . '%s names the toolset of every tool it finds.',
            $tool,
            McpToolsetRegistry::TOOL_SEARCH_TOOL,
            McpToolsetRegistry::QUERY_PARAMETER,
            $toolset,
            McpToolsetRegistry::TOOL_SEARCH_TOOL,
        );
    }
}
