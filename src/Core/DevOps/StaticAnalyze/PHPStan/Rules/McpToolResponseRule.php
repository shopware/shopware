<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<Class_>
 *
 * @internal
 */
#[Package('framework')]
class McpToolResponseRule implements Rule
{
    private const MCP_TOOL_ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';
    private const MCP_TOOL_RESPONSE_CLASS = 'Shopware\Core\Framework\Mcp\Tool\McpToolResponse';

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        if (!$this->hasMcpToolAttribute($node)) {
            return [];
        }

        if ($this->extendsMcpToolResponse($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('MCP tools with #[McpTool] attribute must extend McpToolResponse.')
                ->identifier('shopware.mcpToolMissingResponseClass')
                ->build(),
        ];
    }

    private function extendsMcpToolResponse(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $name = $node->extends->toString();

        return $name === 'McpToolResponse' || $name === self::MCP_TOOL_RESPONSE_CLASS;
    }

    private function hasMcpToolAttribute(Class_ $node): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($attribute->name->toString() === self::MCP_TOOL_ATTRIBUTE) {
                    return true;
                }
            }
        }

        return false;
    }
}
