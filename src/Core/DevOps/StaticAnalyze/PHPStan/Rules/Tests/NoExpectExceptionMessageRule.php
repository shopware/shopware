<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<CallLike>
 *
 * @internal
 */
#[Package('framework')]
class NoExpectExceptionMessageRule implements Rule
{
    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param CallLike $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return [];
        }

        if (!$node->name instanceof Identifier || (string) $node->name !== 'expectExceptionMessage') {
            return [];
        }

        // Catches the call only when it sits inside a TestCase subclass.
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        // Instance-call form ($this->expectExceptionMessage(), $other->expectExceptionMessage()):
        // require the receiver to be a TestCase, so a same-named method on an unrelated object
        // inside a test class is not flagged.
        if ($node instanceof MethodCall) {
            if (!(new ObjectType(TestCase::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
                return [];
            }
        }

        // Static-call form (static::, self::, parent::, ParentTest::expectExceptionMessage()):
        // accept the late-static-binding keywords (already gated by the surrounding-TestCase
        // check above) and any class name that resolves to TestCase or a subclass.
        if ($node instanceof StaticCall) {
            if (!$node->class instanceof Name) {
                return [];
            }

            $className = $node->class->toLowerString();
            if (!\in_array($className, ['self', 'static', 'parent'], true)
                && !(new ObjectType(TestCase::class))->isSuperTypeOf(new ObjectType((string) $node->class))->yes()
            ) {
                return [];
            }
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
