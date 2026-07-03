<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\BCChangeAttributeUsageRule;

/**
 * @internal
 *
 * @extends RuleTestCase<BCChangeAttributeUsageRule>
 */
class BCChangeAttributeUsageRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testStructurallyImpossibleBCChangesAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/BCChangeAttributeUsageRule/BCChangeAttributeUsage.php'], [
            [
                'BecomesFinal on "AlreadyFinalClass": the class is already final.',
                14,
            ],
            [
                'BecomesInternal on "WrongVersionFormatClass": version "6.8.0" must match the format "v6.8.0".',
                19,
            ],
            [
                'BecomesInternal on "AlreadyInternalClass": the class is already @internal.',
                27,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::leadingDollar()": parameter name "$states" must be given without the leading "$".',
                34,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::alreadyExistingParameter()": parameter "existing" already exists.',
                39,
            ],
            [
                'ParameterNameChange on "MethodLevelViolations::missingParameter()": parameter "missing" does not exist.',
                44,
            ],
            [
                'BecomesAbstract on "MethodLevelViolations::alreadyAbstract()": the method is already abstract.',
                49,
            ],
            [
                'VisibilityChange on "MethodLevelViolations::alreadyProtected()": announced visibility "protected" is not narrower than the current visibility.',
                52,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new BCChangeAttributeUsageRule();
    }
}
