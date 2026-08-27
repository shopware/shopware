<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\BCChangeAttributeUsageRule;
use Shopware\Core\Framework\Log\Package;

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
                23,
            ],
            [
                'BecomesInternal on "WrongVersionFormatClass": version "6.8.0" must match the format "v6.8.0".',
                28,
            ],
            [
                'BecomesInternal on "AlreadyInternalClass": the class is already @internal.',
                36,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::leadingDollar()": parameter name "$states" must be given without the leading "$".',
                43,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::alreadyExistingParameter()": parameter "existing" already exists.',
                48,
            ],
            [
                'ParameterNameChange on "MethodLevelViolations::missingParameter()": parameter "missing" does not exist.',
                53,
            ],
            [
                'BecomesAbstract on "MethodLevelViolations::alreadyAbstract()": the method is already abstract.',
                58,
            ],
            [
                'VisibilityChange on "MethodLevelViolations::alreadyProtected()": announced visibility "protected" is not narrower than the current visibility.',
                61,
            ],
            [
                'ReturnTypeNarrowing on "SealedClass::narrowingOnFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                89,
            ],
            [
                'NewOptionalParameter on "SoftSealedClass::newParameterOnSoftFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                106,
            ],
            [
                'ParameterTypeWidening on "ClassWithFinalMethod::wideningOnFinalMethod()": the method is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                114,
            ],
            [
                'ParameterTypeNarrowing on "RuntimeDetectableViolations::narrowingWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                122,
            ],
            [
                'BecomesAbstract on "RuntimeDetectableViolations::becomesAbstractWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                127,
            ],
            [
                'ParameterRemoval on "RuntimeDetectableViolations::removalWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                132,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredAlreadyExists()": parameter "existing" already exists.',
                148,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                154,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::narrowingIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                208,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unchangedIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                216,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::notAThrowable()": announced class "ArrayObject" is not a Throwable.',
                224,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unresolvableExceptionClass()": announced exception "UnimportedException" is not a resolvable class. Reference exception classes via ::class.',
                232,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::missingParameter()": parameter "missing" does not exist.',
                248,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::requiredParameter()": parameter "required" has no current default value.',
                253,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::unchangedDefault()": announced default value for parameter "value" is already current.',
                258,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::requiredParameter()": parameter "required" is required. Removing a required parameter is not actionable before the major release; introduce a new method or factory with the future signature and deprecate the old method instead.',
                276,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::leadingDollar()": parameter name "$optional" must be given without the leading "$".',
                289,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::leadingDollar()": parameter "$optional" does not exist.',
                289,
            ],
            [
                'VisibilityChange on "PropertyLevelViolations::$alreadyProtected": announced visibility "protected" is not narrower than the current visibility.',
                305,
            ],
            [
                'PropertyTypeNarrowing on "PropertyLevelViolations::$unchangedType": announced type "string" is identical to the current property type.',
                308,
            ],
            [
                'BecomesReadonly on "PropertyLevelViolations::$alreadyReadonly": the property is already readonly.',
                311,
            ],
            [
                'PropertyTypeNarrowing on "PromotedPropertyLevelViolations::$unchangedType": announced type "string" is identical to the current property type.',
                318,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited method "inheritedMethod()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as @deprecated, unless the new parent also provides the method.',
                326,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited method "overriddenWithoutDeprecation()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as @deprecated, unless the new parent also provides the method.',
                326,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited method "ancestorMethod()" from "HierarchyRoot" will be removed from the hierarchy. Override it explicitly and mark the override as @deprecated, unless the new parent also provides the method.',
                326,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new BCChangeAttributeUsageRule($this->createReflectionProvider());
    }
}
