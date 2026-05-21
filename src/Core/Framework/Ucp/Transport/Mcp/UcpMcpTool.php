<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Declares a class as a UCP MCP tool. The compiler pass picks up tagged
 * services and registers them with the dispatcher.
 *
 * The `capability` argument links the tool to the underlying UCP capability —
 * if the capability is not in the active intersection, the tool is hidden from
 * `tools/list` responses.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[Package('framework')]
final class UcpMcpTool
{
    public function __construct(
        public readonly string $name,
        public readonly string $capability,
        public readonly string $description,
    ) {
    }
}
