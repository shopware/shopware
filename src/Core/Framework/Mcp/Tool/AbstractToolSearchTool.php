<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpProtocolEra;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractToolSearchTool extends McpToolResponse
{
    final public const NAME = 'shopware-tool-search';

    public function __construct(
        private readonly ?RegistryInterface $registry,
        private readonly ToolSearch $search,
        private readonly ?McpAllowlistProvider $allowlistProvider = null,
        private readonly ?McpToolsetRegistry $toolsetRegistry = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function __invoke(string $query, int $maxResults = 3): McpToolResult
    {
        if ($this->registry === null) {
            return $this->errorResult('MCP registry is not available.');
        }

        $allowlist = $this->allowlistProvider?->forCurrentRequest();
        $stateless = McpProtocolEra::isStateless($this->requestStack?->getMainRequest());
        $tools = [];

        foreach ($this->registry->getTools()->references as $tool) {
            \assert($tool instanceof Tool);

            if ($tool->name === self::NAME) {
                continue;
            }

            if ($stateless && \in_array($tool->name, McpProtocolEra::HIDDEN_ON_STATELESS_ERA, true)) {
                continue;
            }

            if ($allowlist?->tools !== null && !\in_array($tool->name, $allowlist->tools, true)) {
                continue;
            }

            $tools[] = $tool;
        }

        // Without toolsets-list, a stateless client learns the toolset to put into `?toolsets=` from here.
        $toolsetOf = $stateless ? $this->toolsetsByTool() : null;

        $results = [];
        foreach ($this->search->search($tools, $query, min($maxResults, 20)) as $result) {
            $entry = [
                // The SDK Tool serializes itself with the JSON Schema object invariant intact
                // (an empty `properties` stays `{}`), so it is embedded as is.
                'tool' => $result->tool,
                'score' => $result->score,
                'matchedIn' => $result->matchedIn,
            ];

            if ($toolsetOf !== null) {
                $entry['toolset'] = $toolsetOf[$result->tool->name] ?? null;
            }

            $results[] = $entry;
        }

        $meta = [
            'query' => $query,
            'totalCandidates' => \count($tools),
        ];

        $usage = $stateless ? $this->statelessUsageHint() : $this->usageHint();
        if ($usage !== null) {
            $meta['usage'] = $usage;
        }

        return $this->successResult($results, $meta);
    }

    /**
     * Optional guidance appended to the search result telling the model how to make a matched
     * tool callable when the client cannot invoke it directly from the inline result. Null when
     * the scope has no progressive disclosure (e.g. Store API advertises all tools).
     */
    protected function usageHint(): ?string
    {
        return null;
    }

    /**
     * The guidance on the stateless era (2026-07-28), which has no toolset session tools to point to.
     */
    private function statelessUsageHint(): string
    {
        return 'A matched tool may not be advertised in tools/list. Call it by name with tools/call; it does not need '
            . 'to be advertised. If your MCP client only calls advertised tools, reconnect to the MCP endpoint URL with '
            . 'the toolset of the match in the query, for example "?toolsets=<toolset>". Several toolsets are separated by commas.';
    }

    /**
     * @return array<string, string> tool name => toolset
     */
    private function toolsetsByTool(): array
    {
        $toolsetOf = [];

        foreach ($this->toolsetRegistry?->toolsets() ?? [] as $toolset) {
            foreach ($toolset['tools'] as $tool) {
                $toolsetOf[$tool] = $toolset['name'];
            }
        }

        return $toolsetOf;
    }
}
