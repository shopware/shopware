<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('core')]
class CoversAttributeRule implements Rule
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
            return ['Test classes must have CoversClass, CoversFunction or CoversNothing  attribute'];
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
        foreach ($class->getOriginalNode()->attrGroups as $group) {
            $attribute = $group->attrs[0];

            /** @var Node\Name\FullyQualified $name */
            $name = $attribute->name;

            if (\in_array($name->toString(), [CoversClass::class, CoversFunction::class, CoversNothing::class], true)) {
                return true;
            }
        }

        return false;
    }
}
