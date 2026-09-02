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
                28,
            ],
            [
                'BecomesInternal on "WrongVersionFormatClass": version "6.8.0" must match the format "v6.8.0".',
                33,
            ],
            [
                'BecomesInternal on "AlreadyInternalClass": the class is already @internal.',
                41,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::leadingDollar()": parameter name "$states" must be given without the leading "$".',
                48,
            ],
            [
                'NewOptionalParameter on "MethodLevelViolations::alreadyExistingParameter()": parameter "existing" already exists.',
                53,
            ],
            [
                'ParameterNameChange on "MethodLevelViolations::missingParameter()": parameter "missing" does not exist.',
                58,
            ],
            [
                'BecomesAbstract on "MethodLevelViolations::alreadyAbstract()": the method is already abstract.',
                63,
            ],
            [
                'VisibilityChange on "MethodLevelViolations::alreadyProtected()": announced visibility "protected" is not narrower than the current visibility.',
                66,
            ],
            [
                'ReturnTypeNarrowing on "SealedClass::narrowingOnFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                94,
            ],
            [
                'NewOptionalParameter on "SoftSealedClass::newParameterOnSoftFinalClass()": the class is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                111,
            ],
            [
                'ParameterTypeWidening on "ClassWithFinalMethod::wideningOnFinalMethod()": the method is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
                119,
            ],
            [
                'ParameterTypeNarrowing on "RuntimeDetectableViolations::narrowingWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                127,
            ],
            [
                'BecomesAbstract on "RuntimeDetectableViolations::becomesAbstractWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                132,
            ],
            [
                'ParameterRemoval on "RuntimeDetectableViolations::removalWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                137,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredAlreadyExists()": parameter "existing" already exists.',
                153,
            ],
            [
                'NewRequiredParameter on "NewRequiredParameterCases::requiredWithoutTrigger()": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
                159,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::narrowingIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                213,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unchangedIsCovered()": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
                221,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::notAThrowable()": announced class "ArrayObject" is not a Throwable.',
                229,
            ],
            [
                'ExceptionChange on "ExceptionChangeCases::unresolvableExceptionClass()": announced exception "UnimportedException" is not a resolvable class. Reference exception classes via ::class.',
                237,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::missingParameter()": parameter "missing" does not exist.',
                253,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::requiredParameter()": parameter "required" has no current default value.',
                258,
            ],
            [
                'ParameterDefaultValueChange on "ParameterDefaultValueChangeCases::unchangedDefault()": announced default value for parameter "value" is already current.',
                263,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::requiredParameter()": parameter "required" is required. Removing a required parameter is not actionable before the major release; introduce a new method or factory with the future signature and deprecate the old method instead.',
                281,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::leadingDollar()": parameter name "$optional" must be given without the leading "$".',
                294,
            ],
            [
                'ParameterRemoval on "ParameterRemovalCases::leadingDollar()": parameter "$optional" does not exist.',
                294,
            ],
            [
                'VisibilityChange on "PropertyLevelViolations::$alreadyProtected": announced visibility "protected" is not narrower than the current visibility.',
                310,
            ],
            [
                'PropertyTypeNarrowing on "PropertyLevelViolations::$unchangedType": announced type "string" is identical to the current property type.',
                313,
            ],
            [
                'BecomesReadonly on "PropertyLevelViolations::$alreadyReadonly": the property is already readonly.',
                316,
            ],
            [
                'PropertyTypeNarrowing on "PromotedPropertyLevelViolations::$unchangedType": announced type "string" is identical to the current property type.',
                323,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "inheritedMethod()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                335,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "ancestorMethod()" from "HierarchyRoot" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                335,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "providedByProtectedNewParent()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                335,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": non-deprecated method "overriddenWithoutDeprecation()" must not call parent:: because its parent hierarchy will change.',
                335,
            ],
            [
                'ClassHierarchyChange on "InvalidHierarchyChange": inherited public method "providedByTrait()" from "OldHierarchyParent" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                335,
            ],
            [
                'NamespaceChange on "NamespaceChangeWithWrongLocation": newLocation "Shopware\\Core\\Some\\Other\\Location" must be the fully qualified class name ending in "NamespaceChangeWithWrongLocation". A namespace move keeps the class name.',
                376,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new BCChangeAttributeUsageRule($this->createReflectionProvider());
    }
}
