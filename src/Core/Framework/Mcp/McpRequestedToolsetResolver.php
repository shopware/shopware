<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Reads the toolsets a client pinned in its connect URL.
 *
 *     /api/_mcp?toolsets=order,media
 *     /api/_mcp?toolsets=all
 *
 * Progressive disclosure assumes a client that enables a toolset and then re-reads `tools/list`.
 * Some clients enumerate tools once per connection and never ask again, so `shopware-toolset-enable`
 * arrives after the only moment it could have mattered. Inline definitions do not help either: a
 * client may only load a tool whose definition was part of that first enumeration.
 *
 * The connect URL is the one place where the selection can be fixed before that enumeration without
 * server-side state. Clients POST every request to the URL they were configured with, so the
 * parameter is present on `tools/list` and on every call after it.
 *
 * This widens visibility only. The result stays intersected with the principal's MCP allowlist, and
 * every tool checks its own ACL privileges, so editing the query string reaches nothing the
 * credential could not already call.
 *
 * @internal
 */
#[Package('framework')]
class McpRequestedToolsetResolver
{
    final public const QUERY_PARAMETER = 'toolsets';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * Returns the raw toolset names from the query string, trimmed and deduplicated. Validation and
     * the `all` shorthand are resolved by {@see McpToolsetRegistry::expandToolsetNames()}.
     *
     * @return list<string>
     */
    public function resolve(): array
    {
        // The main request, because app scripts are dispatched as internal subrequests that carry
        // none of the client's query string.
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return [];
        }

        $raw = $request->query->get(self::QUERY_PARAMETER);
        if (!\is_string($raw)) {
            return [];
        }

        $names = array_filter(array_map(trim(...), explode(',', $raw)), static fn (string $name): bool => $name !== '');

        return array_values(array_unique($names));
    }
}
