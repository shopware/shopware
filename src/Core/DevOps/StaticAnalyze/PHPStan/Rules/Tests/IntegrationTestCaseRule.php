<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class IntegrationTestCaseRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if ($classReflection->getName() === IntegrationTestCase::class) {
            return [];
        }

        if (!$classReflection->is(TestCase::class)) {
            return [];
        }

        $hasIntegrationTestBehaviour = false;
        foreach ($classReflection->getTraits() as $trait) {
            if ($trait->getName() !== IntegrationTestBehaviour::class) {
                continue;
            }

            $hasIntegrationTestBehaviour = true;
            break;
        }

        if (!$hasIntegrationTestBehaviour) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                '%s should extend %s instead of using trait %s directly.',
                $classReflection->getDisplayName(),
                IntegrationTestCase::class,
                IntegrationTestBehaviour::class
            ))->identifier('shopware.integrationTest')
                ->tip('For PHPStan performance reasons.')
                ->fixNode($node->getOriginalNode(), static function (Node\Stmt\ClassLike $class) {
                    if (!$class instanceof Node\Stmt\Class_) {
                        return $class;
                    }

                    $class->extends = new Node\Name\FullyQualified(IntegrationTestCase::class);
                    $class->stmts = \array_filter($class->stmts, static function (Node\Stmt $stmt) {
                        if (!$stmt instanceof Node\Stmt\TraitUse) {
                            return true;
                        }

                        foreach ($stmt->traits as $traitName) {
                            if ($traitName->toString() === IntegrationTestBehaviour::class) {
                                return false;
                            }
                        }

                        return true;
                    });

                    return $class;
                })
                ->build(),
        ];
    }
}
