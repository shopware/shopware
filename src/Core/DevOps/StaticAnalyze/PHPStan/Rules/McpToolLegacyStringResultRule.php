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
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Reports MCP tools that return a hand-built result string instead of `McpToolResult`. A string can only
 * carry the legacy `{"success": …}` envelope, which is removed in 6.8.0, and neither a summary nor error
 * details.
 *
 * The tool method is `__invoke()` for a `#[McpTool]` on the class, or the method that carries the attribute.
 * It is reported when its native return type is `string`, `?string` or a union containing `string`.
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

        $errors = [];
        foreach ($class->getMethods() as $method) {
            $isToolMethod = self::hasMcpToolAttribute($method->attrGroups)
                || ($classIsTool && $method->name->toLowerString() === '__invoke');

            if (!$isToolMethod || !self::returnsString($method)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'MCP tool method %s::%s() must return McpToolResult, built with successResult() or errorResult(), instead of a hand-built result string.',
                $node->getClassReflection()->getName(),
                $method->name->toString(),
            ))
                ->identifier(self::IDENTIFIER)
                ->line($method->getStartLine())
                ->tip('The legacy {"success": …} string is deprecated and no longer supported with 6.8.0.')
                ->build();
        }

        return $errors;
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
