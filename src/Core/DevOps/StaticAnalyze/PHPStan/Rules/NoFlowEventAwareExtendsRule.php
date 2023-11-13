<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class NoFlowEventAwareExtendsRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $reflection = $node->getClassReflection();

        if (!$reflection->isInterface()) {
            return [];
        }

        if (!$reflection->isSubclassOf(FlowEventAware::class)) {
            return [];
        }

        $text = $node->getDocComment()?->getText() ?? '';

        if (\str_contains($text, '@deprecated tag:v6.6.0 - Will be removed')) {
            return [];
        }

        if (\str_contains($text, '@deprecated tag:v6.6.0 - reason:class-hierarchy-change')) {
            return [];
        }

        return [
            \sprintf('Class %s should not extend FlowEventAware. Flow events should not be derived from each other to make them easier to test', $reflection->getName()),
        ];
    }
}
