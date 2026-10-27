<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * Reports Admin API ACL in Store API MCP tools: a `#[McpToolRequires]` attribute or a
 * `requirePrivilege()` or `requirePrivilegeResult()` call. Store API tools run in a sales channel context, where Admin ACL
 * privileges do not exist, so such a check always passes and suggests a protection that is not
 * there.
 *
 * Only the tool class itself is searched, not methods it inherits from a parent class or a trait.
 * An inherited call is harmless: outside an Admin API context, Context::isAllowed() always passes.
 *
 * Which server a tool belongs to comes from the container, see McpToolScopeResolver. A tool that is
 * also registered on the Admin API server is checked by McpAdminToolRequiresPrivilegeRule as well, so
 * it can only declare `#[McpToolRequiresNoPrivilege]`.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class McpStoreApiToolAdminAclRule implements Rule
{
    private const MCP_TOOL_REQUIRES_ATTRIBUTE = 'Shopware\Core\Framework\Mcp\Attribute\McpToolRequires';

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
        if (!$class instanceof Class_ || $class->isAbstract() || !McpToolScopeResolver::isMcpTool($node->getClassReflection())) {
            return [];
        }

        $className = $node->getClassReflection()->getName();
        if (!$this->scopeResolver->hasScope($className, McpToolScopeResolver::STORE_API)) {
            return [];
        }

        $errors = [];
        foreach (McpToolScopeResolver::attributesOf($class->attrGroups, self::MCP_TOOL_REQUIRES_ATTRIBUTE) as $attribute) {
            $errors[] = $this->error(
                \sprintf('Store API MCP tool %s must not declare #[McpToolRequires]. Admin ACL privileges do not apply in a sales channel context.', $className),
                $attribute->getStartLine(),
            );
        }

        foreach ($this->requirePrivilegeCalls($class->stmts) as $call) {
            $errors[] = $this->error(
                \sprintf('Store API MCP tool %s must not call %s(). Admin ACL privileges do not apply in a sales channel context.', $className, $call->name instanceof Identifier ? $call->name->toString() : 'requirePrivilege'),
                $call->getStartLine(),
            );
        }

        return $errors;
    }

    /**
     * Calls of `requirePrivilege()` or `requirePrivilegeResult()` on the tool itself: `$this->`, `self::`, `static::` and `parent::`.
     *
     * Nested classes, including anonymous ones, are skipped: their `$this`, `self` and `parent` are not the tool.
     * Closures are searched, since a non-static closure binds `$this` to the tool.
     *
     * @param array<Node> $stmts
     *
     * @return list<MethodCall|StaticCall>
     */
    private function requirePrivilegeCalls(array $stmts): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            private const REQUIRE_PRIVILEGE_METHODS = ['requireprivilege', 'requireprivilegeresult'];

            /**
             * @var list<MethodCall|StaticCall>
             */
            public array $calls = [];

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof ClassLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($this->isRequirePrivilegeCallOnTool($node)) {
                    \assert($node instanceof MethodCall || $node instanceof StaticCall);
                    $this->calls[] = $node;
                }

                return null;
            }

            private function isRequirePrivilegeCallOnTool(Node $node): bool
            {
                if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
                    return false;
                }

                if (!$node->name instanceof Identifier || !\in_array($node->name->toLowerString(), self::REQUIRE_PRIVILEGE_METHODS, true)) {
                    return false;
                }

                if ($node instanceof MethodCall) {
                    return $node->var instanceof Variable && $node->var->name === 'this';
                }

                return $node->class instanceof Name
                    && \in_array($node->class->toLowerString(), ['self', 'static', 'parent'], true);
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->calls;
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
