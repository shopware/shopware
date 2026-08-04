<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpToolSchemaNormalizer;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Util\Json;

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
    ) {
    }

    public function __invoke(string $query, int $maxResults = 3): string
    {
        if ($this->registry === null) {
            return $this->error('MCP registry is not available.');
        }

        $allowlist = $this->allowlistProvider?->forCurrentRequest();
        $tools = [];

        foreach ($this->registry->getTools()->references as $tool) {
            \assert($tool instanceof Tool);

            if ($tool->name === self::NAME) {
                continue;
            }

            if ($allowlist?->tools !== null && !\in_array($tool->name, $allowlist->tools, true)) {
                continue;
            }

            $tools[] = $tool;
        }

        $results = [];
        foreach ($this->search->search($tools, $query, min($maxResults, 20)) as $result) {
            $toolData = json_decode(Json::encode($result->tool), true, 512, \JSON_THROW_ON_ERROR);
            \assert(\is_array($toolData));

            // Encoding the Tool and decoding as an associative array collapses an empty
            // `properties` object to `[]`; re-establish the JSON Schema object invariant so the
            // embedded definition stays valid for strict clients (same fix as the transport).
            $toolData = McpToolSchemaNormalizer::normalizeTool($toolData);

            $results[] = [
                'tool' => $toolData,
                'score' => $result->score,
                'matchedIn' => $result->matchedIn,
            ];
        }

        $meta = [
            'query' => $query,
            'totalCandidates' => \count($tools),
        ];

        $usage = $this->usageHint();
        if ($usage !== null) {
            $meta['usage'] = $usage;
        }

        return $this->success($results, $meta);
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
}
