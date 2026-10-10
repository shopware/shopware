<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;

/**
 * Reports MCP tools in the `discovery` group, which is reserved for the core discovery tools. Unit tests
 * are skipped, because they claim the group on purpose to cover the fallback.
 *
 * @implements Rule<Class_>
 *
 * @internal
 */
#[Package('framework')]
class McpReservedToolGroupRule implements Rule
{
    private const MCP_TOOL_ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const MCP_TOOL_GROUP_ATTRIBUTE = 'Shopware\Core\Framework\Mcp\Attribute\McpToolGroup';

    private const UNIT_TEST_NAMESPACE = 'Shopware\\Tests\\Unit\\';

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (str_starts_with($scope->getNamespace() ?? '', self::UNIT_TEST_NAMESPACE)) {
            return [];
        }

        $group = $this->attributeArgument($node, self::MCP_TOOL_GROUP_ATTRIBUTE, 'group', $scope);
        if ($group !== McpToolsetRegistry::DISCOVERY_GROUP) {
            return [];
        }

        $name = $this->attributeArgument($node, self::MCP_TOOL_ATTRIBUTE, 'name', $scope);
        if ($name === null || \in_array($name, McpToolsetRegistry::DISCOVERY_META_TOOLS, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'MCP tool "%s" must not use the reserved "%s" group. Use a group of its own and select it with ?toolsets=.',
                $name,
                McpToolsetRegistry::DISCOVERY_GROUP,
            ))
                ->identifier('shopware.mcpReservedToolGroup')
                ->build(),
        ];
    }

    /**
     * The constant string value of a named (or first positional) attribute argument, or null.
     *
     * Looks at the class first and then at `__invoke()`, per attribute, in the same order as
     * `McpToolAttributeReader` at runtime: a tool may declare `#[McpTool]` and `#[McpToolGroup]` on
     * either, or split them between the two.
     */
    private function attributeArgument(Class_ $node, string $attributeClass, string $argumentName, Scope $scope): ?string
    {
        $attributes = $this->attributesOf($node->attrGroups, $attributeClass);
        $invoke = $node->getMethod('__invoke');
        if ($attributes === [] && $invoke !== null) {
            $attributes = $this->attributesOf($invoke->attrGroups, $attributeClass);
        }

        foreach ($attributes as $attribute) {
            foreach ($attribute->args as $position => $arg) {
                if ($arg->name?->toString() !== $argumentName && ($arg->name !== null || $position !== 0)) {
                    continue;
                }

                $values = $scope->getType($arg->value)->getConstantStrings();

                return \count($values) === 1 ? $values[0]->getValue() : null;
            }
        }

        return null;
    }

    /**
     * @param array<AttributeGroup> $attrGroups
     *
     * @return list<Attribute>
     */
    private function attributesOf(array $attrGroups, string $attributeClass): array
    {
        $attributes = [];
        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($attribute->name->toString() === $attributeClass) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }
}
