<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @implements Rule<InClassNode>
 */
class CoversAnnotationRule implements Rule
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
        if ($this->hasCovers($node)) {
            return [];
        }

        if ($this->isTestClass($node)) {
            return ['Test classes must have @covers annotation'];
        }

        return [];
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (!\str_contains($namespace, 'Shopware\\Tests\\Unit\\') && !\str_contains($namespace, 'Shopware\\Tests\\Migration\\')) {
            return false;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }

    private function hasCovers(InClassNode $class): bool
    {
        $doc = $class->getDocComment();

        if ($doc === null) {
            return false;
        }

        return \str_contains($doc->getText(), '@covers') || \str_contains($doc->getText(), '@coversNothing');
    }
}
