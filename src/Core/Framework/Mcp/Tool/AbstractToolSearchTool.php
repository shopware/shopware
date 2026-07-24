<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
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

            $results[] = [
                'tool' => $toolData,
                'score' => $result->score,
                'matchedIn' => $result->matchedIn,
            ];
        }

        return $this->success($results, [
            'query' => $query,
            'totalCandidates' => \count($tools),
        ]);
    }
}
