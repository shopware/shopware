<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
#[Package('core')]
class PHPUnitMethodAttributesOverAnnotationsRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     *
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($scope->getClassReflection() === null || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $annotations = [
            'author',
            'after',
            'afterClass',
            'backupGlobals',
            'backupStaticProperties',
            'before',
            'beforeClass',
            'dataProvider',
            'depends',
            'doesNotPerformAssertions',
            'group',
            'preserveGlobalState',
            'requires',
            'runInSeparateProcess',
            'test',
            'testdox',
            'testWith',
            'ticket',
            'uses',
        ];

        $pattern = '/@(' . implode('|', $annotations) . ')\s+([^\s]+)/';

        if (preg_match($pattern, $docComment->getText(), $matches)) {
            return ['Please use phpunit attribute instead of annotation for: ' . $matches[1]];
        }

        return [];
    }
}
