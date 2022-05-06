<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;

/**
 * @implements Rule<Class_>
 */
class InternalClassRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInternal($node)) {
            return [];
        }

        if ($this->isTestClass($node, $scope)) {
            return ['Test classes must be flagged @internal to not be captured by the BC checker'];
        }

        if ($this->isStorefrontController($scope)) {
            return ['Storefront controllers must be flagged @internal to not be captured by the BC checker. The BC promise is checked over the route annotation.'];
        }

        return [];
    }

    private function isTestClass(Class_ $node, Scope $scope): bool
    {
        $namespace = (string) $node->namespacedName;
        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if ($scope->getClassReflection() === null) {
            return false;
        }

        if ($scope->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $scope->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }

    private function isInternal(Class_ $class): bool
    {
        $doc = $class->getDocComment();

        if ($doc === null) {
            return false;
        }

        return \str_contains($doc->getText(), '@internal');
    }

    private function isStorefrontController(Scope $scope): bool
    {
        $class = $scope->getClassReflection();

        if ($class === null) {
            return false;
        }

        if ($class->getParentClass() === null) {
            return false;
        }

        return $class->getParentClass()->getName() === 'Shopware\Storefront\Controller\StorefrontController';
    }
}
