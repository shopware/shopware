<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Declares that an Admin API MCP tool needs no ACL privilege, for example because it reads no shop data.
 * It is the explicit opt-out from #[McpToolRequires]: the Shopware PHPStan rule
 * `shopware.mcpAdminToolMissingRequires` expects every Admin API tool to declare exactly one of the two.
 *
 * Example:
 *   #[McpTool(name: 'my-entity-schema', description: '...')]
 *   #[McpToolRequiresNoPrivilege(reason: 'Reads entity definitions only, never a repository.')]
 *   class MyEntitySchemaTool extends McpToolResponse { ... }
 *
 * IMPORTANT: Like #[McpToolRequires], this attribute is purely informational. It grants nothing at runtime:
 * the DAL keeps checking the ACL of every repository operation the tool performs.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class McpToolRequiresNoPrivilege
{
    /**
     * @param string|null $reason Why the tool needs no privilege, for reviewers and operators
     */
    public function __construct(public ?string $reason = null)
    {
    }
}
