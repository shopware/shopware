<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Declares the ACL privileges a tool requires to function.
 * Apply repeatedly to list multiple requirements.
 *
 * Two mutually exclusive forms are supported — use only one per attribute instance:
 *
 * Static (known at compile time):
 *   #[McpToolRequires('system_config:read')]
 *
 * Dynamic (privilege depends on a runtime parameter):
 *   #[McpToolRequires(entityParam: 'entity', operations: ['read'])]
 *   — renders as "<entity>:read" in the UI and CLI
 *
 * IMPORTANT: This attribute is purely informational for operators configuring
 * ACL roles. It does NOT enforce privileges at runtime. Actual enforcement
 * happens via McpToolResponse::requirePrivilege() calls inside __invoke()
 * and the DAL's own ACL checks on every repository operation.
 *
 * @codeCoverageIgnore
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class McpToolRequires
{
    /**
     * @param list<string> $operations
     */
    public function __construct(
        public readonly ?string $privilege = null,
        public readonly ?string $entityParam = null,
        public readonly array $operations = [],
    ) {
    }
}
