<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\ExceptionChange;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Feature;

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
    public function emptyAnnouncement(): void
    {
    }
}
