<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[Package('framework')]
final readonly class McpToolGroup
{
    public function __construct(public string $group)
    {
    }
}
