<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\NewRequiredParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Feature;
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

class UnresolvableTypePayloads
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'UnimportedShortName')]
    public function shortClassName(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'narrowing');

        return null;
    }
}
