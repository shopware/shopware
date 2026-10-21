<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * Reports Admin API MCP tools that do not declare `#[McpToolRequires]`. The attribute is what
 * `debug:mcp`, `/api/_action/mcp/tools` and the integration allowlist show operators, so a tool
 * without it looks like it needs no privilege at all.
 *
 * Which server a tool belongs to comes from the container, see McpToolScopeResolver. Store API tools
 * are covered by McpStoreApiToolAdminAclRule instead.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class McpAdminToolRequiresPrivilegeRule implements Rule
{
    private const MCP_TOOL_REQUIRES_ATTRIBUTE = 'Shopware\Core\Framework\Mcp\Attribute\McpToolRequires';

    /**
     * Core tools that read no shop data and so need no privilege. Audited, keep it short:
     * extensions get no entry here.
     */
    private const TOOLS_WITHOUT_PRIVILEGES = [
        // Reads entity definitions only, never a repository.
        'Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool',
        // Discovery meta-tools: they only return capability names the caller's allowlist already permits.
        'Shopware\Core\Framework\Mcp\Tool\ToolSearchTool',
        'Shopware\Core\Framework\Mcp\Tool\ToolsetEnableTool',
        'Shopware\Core\Framework\Mcp\Tool\ToolsetsListTool',
    ];

    private readonly McpToolScopeResolver $scopeResolver;

    public function __construct(ServiceMap $serviceMap)
    {
        $this->scopeResolver = new McpToolScopeResolver($serviceMap);
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getOriginalNode();
        if (!$class instanceof Class_ || $class->isAbstract() || !McpToolScopeResolver::isMcpTool($class)) {
            return [];
        }

        $className = $node->getClassReflection()->getName();
        if (\in_array($className, self::TOOLS_WITHOUT_PRIVILEGES, true)) {
            return [];
        }

        if ($this->scopeResolver->resolve($className) !== McpToolScopeResolver::ADMIN_API) {
            return [];
        }

        if (McpToolScopeResolver::attributesOf($class->attrGroups, self::MCP_TOOL_REQUIRES_ATTRIBUTE) !== []) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Admin API MCP tool %s must declare the ACL privileges it needs with #[McpToolRequires] on the class. Operators and the integration allowlist read them from there.',
                $className,
            ))
                ->identifier('shopware.mcpAdminToolMissingRequires')
                ->tip('The attribute is informational. Keep enforcing the privileges with requirePrivilege() and the DAL.')
                ->build(),
        ];
    }
}
