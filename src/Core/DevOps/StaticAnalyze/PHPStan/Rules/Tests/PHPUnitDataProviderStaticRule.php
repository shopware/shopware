<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<Node\Stmt\ClassMethod>
 *
 * @internal
 */
#[Package('core')]
class PHPUnitDataProviderStaticRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param Node\Stmt\ClassMethod $node
     *
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Check if the method has a dataProvider annotation
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $dataProviderPattern = '/@dataProvider\s+([^\s]+)/';
        if (!preg_match($dataProviderPattern, $docComment->getText(), $matches)) {
            return [];
        }

        // Get the dataProvider method name
        $dataProviderName = $matches[1];

        // Find the dataProvider method in the same class
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            throw new ShouldNotHappenException();
        }

        $dataProviderMethod = $classReflection->getNativeMethod($dataProviderName);

        // Check if the dataProvider method is static
        if (!$dataProviderMethod->isStatic()) {
            return [sprintf('DataProvider method %s should be static.', $dataProviderName)];
        }

        return [];
    }
}
