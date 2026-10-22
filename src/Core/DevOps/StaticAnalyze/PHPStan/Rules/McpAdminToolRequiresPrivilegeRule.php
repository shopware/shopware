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
 * Reports Admin API MCP tools that declare neither `#[McpToolRequires]` nor the explicit opt-out
 * `#[McpToolRequiresNoPrivilege]`, or both of them. `#[McpToolRequires]` is what `debug:mcp`,
 * `/api/_action/mcp/tools` and the integration allowlist show operators, so a tool without it looks
 * like it needs no privilege at all; the opt-out makes that a reviewed decision instead of an omission.
 *
 * Which server a tool belongs to comes from the container, see McpToolScopeResolver. A tool that is
 * also registered on the Store API server is checked by McpStoreApiToolAdminAclRule as well.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class McpAdminToolRequiresPrivilegeRule implements Rule
{
    private const MCP_TOOL_REQUIRES_ATTRIBUTE = 'Shopware\Core\Framework\Mcp\Attribute\McpToolRequires';

    private const MCP_TOOL_REQUIRES_NO_PRIVILEGE_ATTRIBUTE = 'Shopware\Core\Framework\Mcp\Attribute\McpToolRequiresNoPrivilege';

    private const IDENTIFIER = 'shopware.mcpAdminToolMissingRequires';

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
        if (!$class instanceof Class_ || $class->isAbstract() || !McpToolScopeResolver::isMcpTool($node->getClassReflection())) {
            return [];
        }

        $className = $node->getClassReflection()->getName();
        if (!$this->scopeResolver->hasScope($className, McpToolScopeResolver::ADMIN_API)) {
            return [];
        }

        $requires = McpToolScopeResolver::attributesOf($class->attrGroups, self::MCP_TOOL_REQUIRES_ATTRIBUTE);
        $noPrivilege = McpToolScopeResolver::attributesOf($class->attrGroups, self::MCP_TOOL_REQUIRES_NO_PRIVILEGE_ATTRIBUTE);

        if ($requires !== [] && $noPrivilege !== []) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Admin API MCP tool %s must not declare both #[McpToolRequires] and #[McpToolRequiresNoPrivilege]. Keep the one that is true.',
                    $className,
                ))
                    ->identifier(self::IDENTIFIER)
                    ->line($noPrivilege[0]->getStartLine())
                    ->build(),
            ];
        }

        if ($requires !== [] || $noPrivilege !== []) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Admin API MCP tool %s must declare the ACL privileges it needs with #[McpToolRequires] on the class, or #[McpToolRequiresNoPrivilege] if it needs none. Operators and the integration allowlist read them from there.',
                $className,
            ))
                ->identifier(self::IDENTIFIER)
                ->tip('Both attributes are informational. Keep enforcing the privileges with requirePrivilege() and the DAL.')
                ->build(),
        ];
    }
}
