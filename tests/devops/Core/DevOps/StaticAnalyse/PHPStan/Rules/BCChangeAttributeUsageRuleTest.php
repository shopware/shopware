<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\BCChangeAttributeUsageRule;

/**
 * @internal
 *
 * @extends RuleTestCase<BCChangeAttributeUsageRule>
 */
#[Package('framework')]
class BCChangeAttributeUsageRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testStructurallyImpossibleBCChangesAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/BCChangeAttributeUsageRule/BCChangeAttributeUsage.php'], [
            [
                'BecomesFinal on "AlreadyFinalClass": the class is already final.',
                20,
            ],
            [
                'BecomesInternal on "WrongVersionFormatClass": version "6.8.0" must match the format "v6.8.0".',
                25,
            ],
            [
                'BecomesInternal on "AlreadyInternalClass": the class is already @internal.',
                33,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::leadingDollar()": parameter name "$states" must be given without the leading "$".',
                40,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::alreadyExistingParameter()": parameter "existing" already exists.',
                45,
            ],
            [
                'ParameterNameChange on "MethodLevelViolations::missingParameter()": parameter "missing" does not exist.',
                50,
            ],
            [
                'BecomesAbstract on "MethodLevelViolations::alreadyAbstract()": the method is already abstract.',
                55,
            ],
            [
                'VisibilityChange on "MethodLevelViolations::alreadyProtected()": announced visibility "protected" is not narrower than the current visibility.',
                58,
            ],
            [
                'ReturnTypeNarrowing on "SealedClass::narrowingOnFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                86,
            ],
            [
                'NewOptionalParameter on "SoftSealedClass::newParameterOnSoftFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                103,
            ],
            [
                'ParameterTypeWidening on "ClassWithFinalMethod::wideningOnFinalMethod()": the method is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                111,
            ],
            [
                'ParameterTypeNarrowing on "RuntimeDetectableViolations::narrowingWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                119,
            ],
            [
                'BecomesAbstract on "RuntimeDetectableViolations::becomesAbstractWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                124,
            ],
            [
                'ParameterRemoval on "RuntimeDetectableViolations::removalWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                129,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredAlreadyExists()": parameter "existing" already exists.',
                145,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                151,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::narrowingIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                205,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unchangedIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                213,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::notAThrowable()": announced class "ArrayObject" is not a Throwable.',
                221,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unresolvableExceptionClass()": announced exception "UnimportedException" is not a resolvable class. Reference exception classes via ::class.',
                229,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::emptyAnnouncement()": "newExceptions" must announce at least one exception class.',
                237,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new BCChangeAttributeUsageRule($this->createReflectionProvider());
    }
}
