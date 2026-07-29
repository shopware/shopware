<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Declares a functional dependency on another MCP tool by name.
 * Apply repeatedly to list multiple dependencies.
 *
 * The compiler pass validates that every declared dependency name is registered
 * in the container — unknown names cause a build-time exception.
 *
 * At runtime the allowlist provider automatically includes all transitive
 * dependencies so an AI agent never calls an allowed tool whose peer is blocked.
 *
 * Example:
 *   #[McpTool(name: 'my-checkout', description: '...')]
 *   #[McpToolDependsOn('my-cart-manage')]
 *   class MyCheckoutTool extends McpToolResponse { ... }
 *
 * @codeCoverageIgnore
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class McpToolDependsOn
{
    public function __construct(
        public readonly string $toolName,
    ) {
    }
}
