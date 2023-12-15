<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class PHPUnitClassAttributesOverAnnotationsRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!TestRuleHelper::isTestClass($node->getClassReflection())) {
            return [];
        }

        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $annotations = [
            'backupGlobals',
            'backupStaticProperties',
            'covers',
            'coversDefaultClass',
            'coversNothing',
            'doesNotPerformAssertions',
            'group',
            'large',
            'medium',
            'preserveGlobalState',
            'requires',
            'runTestsInSeparateProcesses',
            'small',
            'testdox',
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
