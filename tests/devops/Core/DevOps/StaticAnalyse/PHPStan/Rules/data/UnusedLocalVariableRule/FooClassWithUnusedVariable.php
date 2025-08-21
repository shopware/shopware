<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UnusedLocalVariableRule;

/**
 * @internal
 */
class FooClassWithUnusedVariable
{
    /**
     * single unused variable - should be reported
     */
    public function bar(): void
    {
        $unusedVariable = 'This variable is not used anywhere in this method';
    }

    /**
     * single used variable - should not be reported
     */
    public function baz(): string
    {
        $usedVariable = 'This variable is used';

        return $usedVariable;
    }
}
