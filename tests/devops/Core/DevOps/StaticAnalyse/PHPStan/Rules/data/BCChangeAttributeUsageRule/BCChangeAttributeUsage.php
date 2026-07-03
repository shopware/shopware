<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;

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
    }
}
