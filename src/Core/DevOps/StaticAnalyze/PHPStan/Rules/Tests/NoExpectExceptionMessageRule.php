<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoExpectExceptionMessageRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        if (!$node->name instanceof Identifier || (string) $node->name !== 'expectExceptionMessage') {
            return [];
        }

        if (!(new ObjectType(TestCase::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'expectExceptionMessage() is soft-deprecated in PHPUnit 13.2 and scheduled for removal in 15.0. Use expectExceptionObject(new YourException(...)) so the exception class, code and message are asserted from a single source of truth.'
            )
                ->identifier('shopware.expectExceptionMessage')
                ->build(),
        ];
    }
}
