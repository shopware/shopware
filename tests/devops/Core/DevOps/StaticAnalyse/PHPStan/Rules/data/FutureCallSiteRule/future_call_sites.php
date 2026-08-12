<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\NewRequiredParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterDefaultValueChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;

class BCSubject
{
    #[BecomesInternal(version: 'v6.8.0')]
    public function internalMethod(): void
    {
    }

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    public function becomesProtected(): void
    {
    }

    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options')]
    public function withRemoval(?array $options = null): void
    {
    }

    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'oldName', newName: 'newName')]
    public function withRename(string $oldName): void
    {
    }

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'id', newType: 'string')]
    public function withNarrowing(int|string $id): void
    {
    }

    #[NewRequiredParameter(version: 'v6.8.0', parameterName: 'context', parameterType: 'string')]
    public function withNewRequired(string $id): void
    {
    }

    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'strict', newDefaultValue: true)]
    public function withDefaultValue(bool $strict = false): void
    {
    }
}

#[BecomesInternal(version: 'v6.8.0')]
class InternalSubject
{
    public function anyMethod(): void
    {
    }
}

class Caller
{
    public function trigger(BCSubject $subject, InternalSubject $internal): void
    {
        $subject->internalMethod();
        $subject->becomesProtected();
        $subject->withRemoval(['a']);
        $subject->withRemoval(options: ['a']);
        $subject->withRemoval();
        $subject->withRename('x');
        $subject->withRename(oldName: 'x');
        $subject->withNarrowing('ok');
        $subject->withNarrowing(123);
        $subject->withNewRequired('id');
        $subject->withNewRequired('id', 'context');
        $subject->withDefaultValue();
        $subject->withDefaultValue(false);
        $internal->anyMethod();
        new InternalSubject();
    }
}

class SubclassCaller extends BCSubject
{
    public function allowed(): void
    {
        $this->becomesProtected();
    }
}
