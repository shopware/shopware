<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * Reports Admin API ACL in Store API MCP tools: a `#[McpToolRequires]` attribute or a
 * `requirePrivilege()` call. Store API tools run in a sales channel context, where Admin ACL
 * privileges do not exist, so such a check either always fails or suggests a protection that is
 * not there.
 *
 * Which server a tool belongs to comes from the container, see McpToolScopeResolver.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class McpStoreApiToolAdminAclRule implements Rule
{
    private const MCP_TOOL_REQUIRES_ATTRIBUTE = 'Shopware\Core\Framework\Mcp\Attribute\McpToolRequires';

    private const REQUIRE_PRIVILEGE_METHOD = 'requirePrivilege';

    private const IDENTIFIER = 'shopware.mcpStoreApiToolAdminAcl';

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
        if ($this->scopeResolver->resolve($className) !== McpToolScopeResolver::STORE_API) {
            return [];
        }

        $errors = [];
        foreach (McpToolScopeResolver::attributesOf($class->attrGroups, self::MCP_TOOL_REQUIRES_ATTRIBUTE) as $attribute) {
            $errors[] = $this->error(
                \sprintf('Store API MCP tool %s must not declare #[McpToolRequires]. Admin ACL privileges do not apply in a sales channel context.', $className),
                $attribute->getStartLine(),
            );
        }

        foreach ($this->requirePrivilegeCalls($class) as $call) {
            $errors[] = $this->error(
                \sprintf('Store API MCP tool %s must not call requirePrivilege(). Admin ACL privileges do not apply in a sales channel context.', $className),
                $call->getStartLine(),
            );
        }

        return $errors;
    }

    /**
     * Calls on the tool itself: `$this->requirePrivilege()`, `self::`, `static::` and `parent::`.
     *
     * @return list<MethodCall|StaticCall>
     */
    private function requirePrivilegeCalls(Class_ $class): array
    {
        /** @var list<MethodCall|StaticCall> $calls */
        $calls = (new NodeFinder())->find($class->stmts, static function (Node $node): bool {
            if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
                return false;
            }

            if (!$node->name instanceof Identifier || $node->name->toLowerString() !== strtolower(self::REQUIRE_PRIVILEGE_METHOD)) {
                return false;
            }

            if ($node instanceof MethodCall) {
                return $node->var instanceof Variable && $node->var->name === 'this';
            }

            return $node->class instanceof Name
                && \in_array($node->class->toLowerString(), ['self', 'static', 'parent'], true);
        });

        return $calls;
    }

    private function error(string $message, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier(self::IDENTIFIER)
            ->line($line)
            ->tip('Store API tools are protected by the sales channel context and the Store API routes they call.')
            ->build();
    }
}
