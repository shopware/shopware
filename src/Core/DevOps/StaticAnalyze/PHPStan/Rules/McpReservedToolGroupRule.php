<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;

/**
 * Reports MCP tools that put themselves into the reserved `discovery` group.
 *
 * Only the core discovery tools belong there: everything in it is advertised on every connection. At
 * runtime a tool that claims it anyway is moved to the fallback toolset, so this rule tells the author
 * before that happens. The supported way to show tools on the first `tools/list` is connect-time
 * selection with `?toolsets=`.
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

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
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
                'MCP tool "%s" must not use the reserved "%s" group, which is limited to the core discovery tools. Give it a group of its own and select that toolset at connect time with ?toolsets=.',
                $name,
                McpToolsetRegistry::DISCOVERY_GROUP,
            ))
                ->identifier('shopware.mcpReservedToolGroup')
                ->build(),
        ];
    }

    /**
     * The constant string value of a named (or first positional) attribute argument, or null.
     */
    private function attributeArgument(Class_ $node, string $attributeClass, string $argumentName, Scope $scope): ?string
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($attribute->name->toString() !== $attributeClass) {
                    continue;
                }

                foreach ($attribute->args as $position => $arg) {
                    if ($arg->name?->toString() !== $argumentName && ($arg->name !== null || $position !== 0)) {
                        continue;
                    }

                    $values = $scope->getType($arg->value)->getConstantStrings();

                    return \count($values) === 1 ? $values[0]->getValue() : null;
                }
            }
        }

        return null;
    }
}
