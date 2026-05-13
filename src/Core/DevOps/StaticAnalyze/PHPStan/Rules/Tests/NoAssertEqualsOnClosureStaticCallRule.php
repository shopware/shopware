<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class NoAssertEqualsOnClosureStaticCallRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        if (!$node->name instanceof Identifier || (string) $node->name !== 'assertEquals') {
            return [];
        }

        return NoAssertEqualsOnClosureRule::checkArgs($node->getArgs(), $scope);
    }
}
