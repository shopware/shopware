<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\UnionType as PhpStanUnionType;
use Shopware\Core\Framework\Log\Package;

/**
 * Reports MCP tools that return a hand-built result string instead of `McpToolResult`. A string can only
 * carry the legacy `{"success": …}` envelope, which is removed in 6.8.0, and neither a summary nor error
 * details.
 *
 * The tool method is `__invoke()` for a `#[McpTool]` on the class, or the method that carries the attribute.
 * It is reported when its native return type is `string`, `?string` or a union containing `string`.
 * An `__invoke()` the class-level tool inherits from a parent class or a trait is resolved through reflection
 * and reported on the tool class, since the MCP runtime calls it on the tool.
 *
 * Only registered in core-rules.neon for now, so extensions are warned by the deprecated string helpers of
 * McpToolResponse instead of a failing build. It moves to rules.neon with 6.8.0.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class McpToolLegacyStringResultRule implements Rule
{
    private const MCP_TOOL_ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const IDENTIFIER = 'shopware.mcpToolLegacyStringResult';

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getOriginalNode();
        if (!$class instanceof Class_) {
            return [];
        }

        $classIsTool = self::hasMcpToolAttribute($class->attrGroups);
        $className = $node->getClassReflection()->getName();

        $errors = [];
        $declaresInvoke = false;
        foreach ($class->getMethods() as $method) {
            $isInvoke = $method->name->toLowerString() === '__invoke';
            $declaresInvoke = $declaresInvoke || $isInvoke;
            $isToolMethod = self::hasMcpToolAttribute($method->attrGroups) || ($classIsTool && $isInvoke);

            if (!$isToolMethod || !self::returnsString($method)) {
                continue;
            }

            $errors[] = self::error($className, $method->name->toString(), $method->getStartLine());
        }

        if ($classIsTool && !$declaresInvoke && self::inheritedInvokeReturnsString($node->getClassReflection())) {
            $errors[] = self::error($className, '__invoke', $class->getStartLine());
        }

        return $errors;
    }

    private static function error(string $className, string $methodName, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(\sprintf(
            'MCP tool method %s::%s() must return McpToolResult, built with successResult() or errorResult(), instead of a hand-built result string.',
            $className,
            $methodName,
        ))
            ->identifier(self::IDENTIFIER)
            ->line($line)
            ->tip('The legacy {"success": …} string is deprecated and no longer supported with 6.8.0.')
            ->build();
    }

    /**
     * `__invoke()` declared by a parent class or a trait. Only the native return type counts, like for declared methods.
     */
    private static function inheritedInvokeReturnsString(ClassReflection $classReflection): bool
    {
        if (!$classReflection->hasNativeMethod('__invoke')) {
            return false;
        }

        $returnType = ParametersAcceptorSelector::combineAcceptors(
            $classReflection->getNativeMethod('__invoke')->getVariants(),
        )->getNativeReturnType();

        $members = $returnType instanceof PhpStanUnionType ? $returnType->getTypes() : [$returnType];
        foreach ($members as $member) {
            if ($member->isString()->yes()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<AttributeGroup> $attrGroups
     */
    private static function hasMcpToolAttribute(array $attrGroups): bool
    {
        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($attribute->name->toString() === self::MCP_TOOL_ATTRIBUTE) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function returnsString(ClassMethod $method): bool
    {
        $type = $method->returnType;

        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $member) {
                if (self::isString($member)) {
                    return true;
                }
            }

            return false;
        }

        return $type !== null && self::isString($type);
    }

    private static function isString(Identifier|Name|ComplexType $type): bool
    {
        return $type instanceof Identifier && $type->toLowerString() === 'string';
    }
}
