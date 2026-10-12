<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Flags assertions that can never run because they are placed after the call an
 * `expectException*()` test expects to throw.
 *
 * PHPUnit aborts the test method at the throwing statement, so in
 *
 *     $this->expectException(FooException::class);
 *     $subject->act();                     // throws
 *     static::assertCount(1, $subject);    // never executed
 *
 * the trailing assertion silently asserts nothing, and the test reads stronger than it is.
 * Mechanically: after the last top-level `expectException*()` call, once any non-assertion
 * statement follows (the act call), every later top-level assertion statement is reported.
 *
 * The fix depends on intent: a "nothing changed" check belongs in a `finally` block wrapping
 * the act call; an assertion meant to run before the throw belongs above the act call; a dead
 * block exercising post-exception behaviour belongs in its own test without `expectException`.
 *
 * Gated to the first-party test namespaces, so consumers of the shared rule set outside those
 * namespaces are not forced into the rule.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoUnreachableAssertionAfterExpectExceptionRule implements Rule
{
    public const ERROR_UNREACHABLE_ASSERTION = 'This assertion is unreachable: the test expects an exception, so PHPUnit aborts the method at the throwing call above. Wrap the throwing call in try/finally, move the assertion before it, or split the test.';

    private const FIRST_PARTY_TEST_NAMESPACES = [
        'Shopware\Tests\\',
        'Shopware\Commercial\Tests\\',
        'Swag\SaasRufus\Test\\',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!TestRuleHelper::isTestClass($node->getClassReflection())) {
            return [];
        }

        if (!self::isFirstPartyTestClass($node->getClassReflection()->getName())) {
            return [];
        }

        $errors = [];
        foreach ($node->getOriginalNode()->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                $errors = [...$errors, ...$this->processMethod($stmt)];
            }
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function processMethod(ClassMethod $method): array
    {
        $stmts = array_values($method->stmts ?? []);

        $expectIndex = null;
        foreach ($stmts as $i => $stmt) {
            if ($this->isTestCaseCall($stmt, 'expectException')) {
                $expectIndex = $i;
            }
        }

        if ($expectIndex === null) {
            return [];
        }

        $errors = [];
        $sawActStatement = false;
        for ($i = $expectIndex + 1, $count = \count($stmts); $i < $count; ++$i) {
            $stmt = $stmts[$i];

            if ($this->isTestCaseCall($stmt, 'assert')) {
                if ($sawActStatement) {
                    $errors[] = RuleErrorBuilder::message(self::ERROR_UNREACHABLE_ASSERTION)
                        ->identifier('shopware.unreachableTestAssertion')
                        ->line($stmt->getStartLine())
                        ->build();
                }

                continue;
            }

            $sawActStatement = true;
        }

        return $errors;
    }

    private static function isFirstPartyTestClass(string $className): bool
    {
        foreach (self::FIRST_PARTY_TEST_NAMESPACES as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches top-level `$this->xxx()` / `static::xxx()` / `self::xxx()` statements whose method
     * name starts with the given prefix, i.e. calls into the TestCase API itself. Calls on other
     * receivers are deliberately not treated as assertions so they count as act statements.
     */
    private function isTestCaseCall(Stmt $stmt, string $methodPrefix): bool
    {
        if (!$stmt instanceof Expression) {
            return false;
        }

        $expr = $stmt->expr;

        if ($expr instanceof MethodCall) {
            $onThis = $expr->var instanceof Variable && $expr->var->name === 'this';

            return $onThis && $expr->name instanceof Identifier && str_starts_with($expr->name->name, $methodPrefix);
        }

        if ($expr instanceof StaticCall) {
            $onOwnClass = $expr->class instanceof Name && \in_array($expr->class->toString(), ['static', 'self'], true);

            return $onOwnClass && $expr->name instanceof Identifier && str_starts_with($expr->name->name, $methodPrefix);
        }

        return false;
    }
}
