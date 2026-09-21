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
                30,
            ],
            [
                'BecomesInternal on "WrongVersionFormatClass": version "6.8.0" must match the format "v6.8.0".',
                35,
            ],
            [
                'BecomesInternal on "AlreadyInternalClass": the class is already @internal.',
                43,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::leadingDollar()": parameter name "$states" must be given without the leading "$".',
                50,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::alreadyExistingParameter()": parameter "existing" already exists.',
                55,
            ],
            [
                'ParameterNameChange on "MethodLevelViolations::missingParameter()": parameter "missing" does not exist.',
                60,
            ],
            [
                'BecomesAbstract on "MethodLevelViolations::alreadyAbstract()": the method is already abstract.',
                65,
            ],
            [
                'VisibilityChange on "MethodLevelViolations::alreadyProtected()": announced visibility "protected" is not narrower than the current visibility.',
                68,
            ],
            [
                'ReturnTypeNarrowing on "SealedClass::narrowingOnFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                96,
            ],
            [
                'NewOptionalParameter on "SoftSealedClass::newParameterOnSoftFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                113,
            ],
            [
                'ParameterTypeWidening on "ClassWithFinalMethod::wideningOnFinalMethod()": the method is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                121,
            ],
            [
                'ParameterTypeNarrowing on "RuntimeDetectableViolations::narrowingWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                129,
            ],
            [
                'BecomesAbstract on "RuntimeDetectableViolations::becomesAbstractWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                134,
            ],
            [
                'ParameterRemoval on "RuntimeDetectableViolations::removalWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                139,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredAlreadyExists()": parameter "existing" already exists.',
                155,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                161,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::narrowingIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                215,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unchangedIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                223,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::notAThrowable()": announced class "ArrayObject" is not a Throwable.',
                231,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unresolvableExceptionClass()": announced exception "UnimportedException" is not a resolvable class. Reference exception classes via ::class.',
                239,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::missingParameter()": parameter "missing" does not exist.',
                255,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::requiredParameter()": parameter "required" has no current default value.',
                260,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::unchangedDefault()": announced default value for parameter "value" is already current.',
                265,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::requiredParameter()": parameter "required" is required. Removing a required parameter is not actionable before the major release; introduce a new method or factory with the future signature and deprecate the old method instead.',
                283,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::leadingDollar()": parameter name "$optional" must be given without the leading "$".',
                296,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::leadingDollar()": parameter "$optional" does not exist.',
                296,
            ],
            [
                'VisibilityChange on "PropertyLevelViolations::$alreadyProtected": announced visibility "protected" is not narrower than the current visibility.',
                312,
            ],
            [
                'PropertyTypeNarrowing on "PropertyLevelViolations::$unchangedType": announced type "string" is identical to the current property type.',
                315,
            ],
            [
                'BecomesReadonly on "PropertyLevelViolations::$alreadyReadonly": the property is already readonly.',
                318,
            ],
            [
                'PropertyTypeNarrowing on "PromotedPropertyLevelViolations::$unchangedType": announced type "string" is identical to the current property type.',
                325,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "inheritedMethod()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                337,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "ancestorMethod()" from "HierarchyRoot" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                337,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "providedByProtectedNewParent()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                337,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": non-deprecated method "overriddenWithoutDeprecation()" must not call parent:: because its parent hierarchy will change.',
                337,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "providedByTrait()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                337,
            ],
            [
                'ExperimentalReplacement on "ExperimentalReplacementLowercaseFeature": feature "fake_feature" must be the ALL_CAPS name of the experimental feature flag.',
                378,
            ],
            [
                'ExperimentalReplacement on "ExperimentalReplacementWithoutTarget": name a replacement class or describe what supersedes the symbol.',
                383,
            ],
            [
                'ExperimentalReplacement on "ExperimentalReplacementUnresolvable": replacement "UnimportedReplacement" is not a resolvable class. Reference the replacement via ::class.',
                388,
            ],
            [
                'ExperimentalReplacement on "ExperimentalReplacementStableTarget": replacement "StableReplacementTarget" is not marked @experimental for feature "FAKE_FEATURE". Once the replacement is stable, turn this attribute into a real @deprecated annotation.',
                393,
            ],
            [
                'ExperimentalReplacement on "ExperimentalReplacementFeatureMismatch": replacement "ExperimentalReplacementTarget" is not marked @experimental for feature "OTHER_FEATURE". Once the replacement is stable, turn this attribute into a real @deprecated annotation.',
                398,
            ],
            [
                'ExperimentalReplacement on "ExperimentalReplacementBlankDescription": name a replacement class or describe what supersedes the symbol.',
                417,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new BCChangeAttributeUsageRule($this->createReflectionProvider());
    }
}
