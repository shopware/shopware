<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Reads the toolsets a client pinned in its connect URL: `/api/_mcp?toolsets=order,media`.
 * See "Tool discovery" in Mcp/AGENTS.md for why the URL is the only place this can be fixed.
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
     * Names are returned verbatim; {@see McpToolsetRegistry::advertisedToolsForNames()} validates them.
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

        // all() rather than get(): InputBag::get() throws a BadRequestException on a non-scalar
        // value, so `?toolsets[]=order` would fail the whole tools/list instead of being ignored.
        // A malformed parameter must never cost a client its tool list.
        $raw = $request->query->all()[self::QUERY_PARAMETER] ?? null;
        if (!\is_string($raw)) {
            return [];
        }

        $names = array_filter(array_map(trim(...), explode(',', $raw)), static fn (string $name): bool => $name !== '');

        return array_values(array_unique($names));
    }
}
