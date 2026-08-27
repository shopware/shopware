<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Deprecation\BCChange\ExceptionChange;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\NewRequiredParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterDefaultValueChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Feature;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\DirectHierarchyMethodTrait;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\NewHierarchyParent;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\OldHierarchyParent;
use Symfony\Component\Routing\Attribute\Route;

#[BecomesFinal(version: 'v6.8.0')]
final class AlreadyFinalClass
{
}

#[BecomesInternal(version: '6.8.0')]
class WrongVersionFormatClass
{
}

/**
 * @internal
 */
#[BecomesInternal(version: 'v6.8.0')]
class AlreadyInternalClass
{
}

abstract class MethodLevelViolations
{
    #[NewOptionalParameter(version: 'v6.8.0', parameterName: '$states', parameterType: 'array')]
    public function leadingDollar(): void
    {
    }

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'existing', parameterType: 'array')]
    public function alreadyExistingParameter(string $existing): void
    {
    }

    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'missing', newName: 'other')]
    public function missingParameter(string $actual): void
    {
    }

    #[BecomesAbstract(version: 'v6.8.0')]
    abstract public function alreadyAbstract(): void;

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    protected function alreadyProtected(): void
    {
    }
}

#[BecomesFinal(version: 'v6.8.0')]
class ValidUsages
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'static')]
    public function validNarrowing(): self
    {
        return $this;
    }

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'states', parameterType: 'array')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'id', newType: 'string')]
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    public function validMethod(int|string $id): void
    {
        if (!\is_string($id)) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing an int $id is deprecated');
        }
    }
}

final class SealedClass
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'static')]
    public function narrowingOnFinalClass(): self
    {
        return $this;
    }

    #[BecomesInternal(version: 'v6.8.0')]
    public function internalOnFinalClassIsAllowed(): void
    {
    }
}

/**
 * @final
 */
class SoftSealedClass
{
    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'states', parameterType: 'array')]
    public function newParameterOnSoftFinalClass(): void
    {
    }
}

class ClassWithFinalMethod
{
    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'value', newType: 'string|int')]
    final public function wideningOnFinalMethod(string $value): void
    {
    }
}

class RuntimeDetectableViolations
{
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'id', newType: 'string')]
    public function narrowingWithoutTrigger(int|string $id): void
    {
    }

    #[BecomesAbstract(version: 'v6.8.0')]
    public function becomesAbstractWithoutTrigger(): void
    {
    }

    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options')]
    public function removalWithoutTrigger(?array $options = null): void
    {
    }

    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'legacy')]
    public function removalWithTrigger(?array $legacy = null): void
    {
        if ($legacy !== null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing $legacy is deprecated');
        }
    }
}

class NewRequiredParameterCases
{
    #[NewRequiredParameter(version: 'v6.8.0', parameterName: 'existing', parameterType: 'string')]
    public function requiredAlreadyExists(string $existing): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'shim');
    }

    #[NewRequiredParameter(version: 'v6.8.0', parameterName: 'context', parameterType: 'string')]
    public function requiredWithoutTrigger(): void
    {
    }

    #[NewRequiredParameter(version: 'v6.8.0', parameterName: 'context', parameterType: 'string')]
    public function requiredWithTrigger(): void
    {
        if (\func_num_args() < 1) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'New required parameter $context missing');
        }
    }

    #[NewRequiredParameter(version: 'v6.8.0', parameterName: 'criteria', parameterType: 'string')]
    #[Route(path: '/store-api/fake', name: 'store-api.fake')]
    public function requiredOnRouteNeedsNoTrigger(): void
    {
    }
}

class ExceptionChangeCases
{
    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\LogicException::class])]
    public function unrelatedExceptionIsARealChange(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\Exception::class])]
    public function wideningIsARealChange(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\UnexpectedValueException::class, \LogicException::class])]
    public function partiallyCoveredIsARealChange(): void
    {
    }

    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\LogicException::class])]
    public function withoutThrowsContractIsNotComparable(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\UnexpectedValueException::class])]
    public function narrowingIsCovered(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\RuntimeException::class])]
    public function unchangedIsCovered(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\ArrayObject::class])]
    public function notAThrowable(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: ['UnimportedException'])]
    public function unresolvableExceptionClass(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [])]
    public function removedExceptionIsARealChange(): void
    {
    }
}

class ParameterDefaultValueChangeCases
{
    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'missing', newDefaultValue: 'new')]
    public function missingParameter(string $value = 'old'): void
    {
    }

    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'required', newDefaultValue: 'new')]
    public function requiredParameter(string $required): void
    {
    }

    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'value', newDefaultValue: 'old')]
    public function unchangedDefault(string $value = 'old'): void
    {
    }

    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'value', newDefaultValue: null)]
    public function defaultChangesToNull(?string $value = 'old'): void
    {
    }

    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'scopes', newDefaultValue: ['system', 'crud'])]
    public function defaultChangesToArray(array $scopes = ['system']): void
    {
    }
}

class ParameterRemovalCases
{
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'required')]
    public function requiredParameter(string $required, ?string $optional = null): void
    {
    }

    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'optional')]
    public function optionalParameter(string $required, ?string $optional = null): void
    {
        if (\func_num_args() > 1) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing $optional is deprecated');
        }
    }

    #[ParameterRemoval(version: 'v6.8.0', parameterName: '$optional')]
    public function leadingDollar(string $required, ?string $optional = null): void
    {
    }

    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'legacy')]
    public function optionalParameterBeforeLaterParameter(?string $legacy = null, ?string $following = null): void
    {
        if ($legacy !== null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing a non-default value for $legacy is deprecated');
        }
    }
}

class PropertyLevelViolations
{
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    protected string $alreadyProtected;

    #[PropertyTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected string $unchangedType;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected readonly string $alreadyReadonly;
}

class PromotedPropertyLevelViolations
{
    public function __construct(
        #[PropertyTypeNarrowing(version: 'v6.8.0', newType: 'string')]
        protected string $unchangedType,
    ) {
    }
}

class ValidPropertyUsage
{
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    public string $becomesProtected;
}

#[ClassHierarchyChange(version: 'v6.8.0', description: 'Changes parent.', newParentClass: NewHierarchyParent::class)]
class InvalidHierarchyChange extends OldHierarchyParent
{
    public function overriddenWithoutDeprecation(): void
    {
    }
}

#[ClassHierarchyChange(version: 'v6.8.0', description: 'Changes parent.', newParentClass: NewHierarchyParent::class)]
class ValidHierarchyChange extends OldHierarchyParent
{
    use DirectHierarchyMethodTrait;

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the old parent.
     */
    public function ancestorMethod(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the old parent.
     */
    public function inheritedMethod(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the old parent.
     */
    public function overriddenWithoutDeprecation(): void
    {
    }
}
