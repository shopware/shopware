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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
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
 * Calls are searched in the tool class and in every method it inherits unchanged from a parent class
 * or a trait, so an Admin API base class does not hide its privilege checks. Methods the tool
 * overrides are skipped, they never run for it.
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

    private const REQUIRE_PRIVILEGE_METHOD = 'requirePrivilege';

    private const IDENTIFIER = 'shopware.mcpStoreApiToolAdminAcl';

    private readonly McpToolScopeResolver $scopeResolver;

    public function __construct(
        ServiceMap $serviceMap,
        private readonly Parser $parser,
    ) {
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

        $classReflection = $node->getClassReflection();
        $className = $classReflection->getName();
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
                \sprintf('Store API MCP tool %s must not call requirePrivilege(). Admin ACL privileges do not apply in a sales channel context.', $className),
                $call->getStartLine(),
            );
        }

        foreach ($this->inheritedMethodsCallingRequirePrivilege($classReflection) as $method) {
            $errors[] = $this->error(
                \sprintf('Store API MCP tool %s must not call requirePrivilege(), but inherits a call from %s(). Admin ACL privileges do not apply in a sales channel context.', $className, $method),
                $class->getStartLine(),
            );
        }

        return $errors;
    }

    /**
     * The inherited methods, as `Declaring::method`, that call requirePrivilege() and run for the tool
     * unchanged: declared in a parent class or a trait and not overridden on the way down.
     *
     * A public or protected method is resolved against the tool, an override anywhere below its
     * declaration replaces it. A private method is resolved against the class it lands in, the parent
     * class or the class using the trait: a same-named method further down does not override it, the
     * parent's own code keeps calling it.
     *
     * @return list<string>
     */
    private function inheritedMethodsCallingRequirePrivilege(ClassReflection $tool): array
    {
        $methods = [];

        foreach ([$tool, ...$tool->getParents()] as $owner) {
            $declarations = $owner === $tool ? [] : [$owner];
            foreach ($this->traitsUsedBy($owner) as $trait) {
                $declarations[] = $trait;
            }

            foreach ($declarations as $declaration) {
                $declarationNode = $this->findClassNode($declaration);
                if ($declarationNode === null) {
                    continue;
                }

                foreach ($declarationNode->getMethods() as $method) {
                    $resolvingClass = $method->isPrivate() ? $owner : $tool;
                    if (!$this->resolvesTo($resolvingClass, $declaration, $method)) {
                        continue;
                    }

                    if ($this->requirePrivilegeCalls($method->stmts ?? []) !== []) {
                        $methods[$declaration->getName() . '::' . $method->name->toString()] = true;
                    }
                }
            }
        }

        return array_keys($methods);
    }

    /**
     * The traits the class uses itself, including traits used by those traits, but not the traits of
     * its parents.
     *
     * @return list<ClassReflection>
     */
    private function traitsUsedBy(ClassReflection $class): array
    {
        $traits = [];
        foreach ($class->getTraits() as $trait) {
            $traits[] = $trait;
            foreach ($this->traitsUsedBy($trait) as $nestedTrait) {
                $traits[] = $nestedTrait;
            }
        }

        return $traits;
    }

    /**
     * Whether calling the method on the class runs the given declaration and not another one.
     */
    private function resolvesTo(ClassReflection $class, ClassReflection $declaration, ClassMethod $method): bool
    {
        $native = $class->getNativeReflection();
        $name = $method->name->toString();
        if (!$native->hasMethod($name)) {
            return false;
        }

        $resolved = $native->getMethod($name);

        return $resolved->getFileName() === $declaration->getFileName() && $resolved->getStartLine() === $method->getStartLine();
    }

    private function findClassNode(ClassReflection $declaration): ?ClassLike
    {
        $file = $declaration->getFileName();
        if ($file === null) {
            return null;
        }

        $shortName = $declaration->getNativeReflection()->getShortName();
        foreach ((new NodeFinder())->findInstanceOf($this->parser->parseFile($file), ClassLike::class) as $classLike) {
            if ($classLike->name?->toString() === $shortName) {
                return $classLike;
            }
        }

        return null;
    }

    /**
     * Calls on the tool itself: `$this->requirePrivilege()`, `self::`, `static::` and `parent::`.
     *
     * @param array<Node> $stmts
     *
     * @return list<MethodCall|StaticCall>
     */
    private function requirePrivilegeCalls(array $stmts): array
    {
        /** @var list<MethodCall|StaticCall> $calls */
        $calls = (new NodeFinder())->find($stmts, static function (Node $node): bool {
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
