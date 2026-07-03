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
                16,
            ],
            [
                'BecomesInternal on "WrongVersionFormatClass": version "6.8.0" must match the format "v6.8.0".',
                21,
            ],
            [
                'BecomesInternal on "AlreadyInternalClass": the class is already @internal.',
                29,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::leadingDollar()": parameter name "$states" must be given without the leading "$".',
                36,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::alreadyExistingParameter()": parameter "existing" already exists.',
                41,
            ],
            [
                'ParameterNameChange on "MethodLevelViolations::missingParameter()": parameter "missing" does not exist.',
                46,
            ],
            [
                'BecomesAbstract on "MethodLevelViolations::alreadyAbstract()": the method is already abstract.',
                51,
            ],
            [
                'VisibilityChange on "MethodLevelViolations::alreadyProtected()": announced visibility "protected" is not narrower than the current visibility.',
                54,
            ],
            [
                'ReturnTypeNarrowing on "SealedClass::narrowingOnFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                82,
            ],
            [
                'NewOptionalParameter on "SoftSealedClass::newParameterOnSoftFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                99,
            ],
            [
                'ParameterTypeWidening on "ClassWithFinalMethod::wideningOnFinalMethod()": the method is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                107,
            ],
            [
                'ParameterTypeNarrowing on "RuntimeDetectableViolations::narrowingWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                115,
            ],
            [
                'BecomesAbstract on "RuntimeDetectableViolations::becomesAbstractWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                120,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new BCChangeAttributeUsageRule($this->createReflectionProvider());
    }
}
