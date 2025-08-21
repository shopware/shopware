<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\UnusedLocalVariableRule;

/**
 * @internal
 *
 * @extends RuleTestCase<UnusedLocalVariableRule>
 */
#[CoversClass(UnusedLocalVariableRule::class)]
class UnusedLocalVariableRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRouteScopeRule(): void
    {
        $this->analyse([__DIR__ . '/data/UnusedLocalVariableRule/FooClassWithUnusedVariable.php'], [
            [
                'Method `bar` has unused local variables: unusedVariable',
                13,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/UnusedLocalVariableRule/FooClassWithUnusedVariables.php'], [
            [
                'Method `baz` has unused local variables: unusedVariable1, unusedVariable2',
                13,
            ],
            [
                'Method `foe` has unused local variables: unusedVariable3, unusedVariable4',
                22,
            ],
            [
                'Method `bar` has unused local variables: unusedVariable5',
                31,
            ],
        ]);
    }

    /**
     * @return UnusedLocalVariableRule
     */
    protected function getRule(): Rule
    {
        return new UnusedLocalVariableRule();
    }
}
