<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UnusedLocalVariableRule;

/**
 * @internal
 */
class FooClassWithUnusedVariables
{
    /**
     * multiple unused variables - should be reported
     */
    public function baz(): void
    {
        $unusedVariable1 = 'This variable is not used anywhere in this method';
        $unusedVariable2 = 'This variable is not used anywhere in this method';
    }

    /**
     * multiple unused variables - should be reported
     */
    public function foe(): void
    {
        $unusedVariable3 = 'This variable is not used anywhere in this method';
        $unusedVariable4 = 'This variable is not used anywhere in this method';
    }

    /**
     * mixed used and unused variables - should report only unused ones
     */
    public function bar(): string
    {
        $unusedVariable5 = 'This variable is not used anywhere in this method';
        $usedVariable = 'This variable is used';

        return $usedVariable;
    }
}
