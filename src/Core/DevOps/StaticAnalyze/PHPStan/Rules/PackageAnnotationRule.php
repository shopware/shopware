<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class PackageAnnotationRule implements Rule
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
        if ($node->getClassReflection()->isAnonymous()) {
            return [];
        }

        if ($this->isTestClass($node)) {
            return [];
        }

        if ($this->hasPackageAnnotation($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('This class is missing the "#[Package(...)]" attribute')
                ->identifier('shopware.missingPackageAttribute')
                ->build(),
        ];
    }

    private function hasPackageAnnotation(InClassNode $class): bool
    {
        foreach ($class->getOriginalNode()->attrGroups as $group) {
            $name = $group->attrs[0]->name;

            if ($name->toString() === Package::class) {
                return true;
            }
        }

        return false;
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, '\\Tests\\') || \str_contains($namespace, '\\Test\\')) {
            return true;
        }

        $file = (string) $node->getClassReflection()->getFileName();
        if (\str_contains($file, '/tests/') || \str_contains($file, '/Tests/') || \str_contains($file, '/Test/')) {
            return true;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }
}
