<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Assigns an MCP tool to a toolset. `$description` describes the whole toolset, not the tool: it is
 * what a model reads in `shopware-toolsets-list` to decide which toolset to enable. Set it on one tool
 * of the group. A description from Shopware's own code always wins: extensions can add tools to a core
 * group, but cannot change its description. Among extensions, the tool whose name sorts first wins.
 * Without a description the toolset gets a generated one.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class McpToolGroup
{
    public function __construct(
        public string $group,
        public ?string $description = null,
    ) {
    }
}
