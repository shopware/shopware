<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Log\Package;

/**
 * Opt-in future-compatibility analysis (see future-compatibility.neon): reports classes
 * whose parents announce extender-affecting BC changes. Where a change can already be
 * anticipated today (covariant narrower returns, contravariant wider parameters, extra
 * optional parameters, overriding a becoming-abstract method), the rule demands exactly
 * that, so the subclass is compatible with the current and the future declaration.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class FutureExtensionRule implements Rule
{
    public function __construct(private readonly AnnouncedTypeResolver $typeResolver)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $errors = [];

        foreach ($class->getParents() as $parent) {
            $errors = [...$errors, ...$this->validateParentClass($class, $parent)];
            $errors = [...$errors, ...$this->validateParentMethods($class, $parent)];
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateParentClass(ClassReflection $class, ClassReflection $parent): array
    {
        $errors = [];
        foreach ($parent->getNativeReflection()->getAttributes() as $attribute) {
            $version = $this->argument($attribute, 'version', 0) ?? '?';
            $errors = [...$errors, ...match ($attribute->getName()) {
                BecomesFinal::class => [$this->error(\sprintf(
                    '"%s" extends "%s", which will become final in %s. There is no forward-compatible way to keep extending it.',
                    $class->getDisplayName(),
                    $parent->getDisplayName(),
                    $version
                ))],
                BecomesInternal::class => [$this->error(\sprintf(
                    '"%s" extends "%s", which will become internal in %s. Stop extending it to stay compatible.',
                    $class->getDisplayName(),
                    $parent->getDisplayName(),
                    $version
                ))],
                ClassHierarchyChange::class => [$this->error(\sprintf(
                    '"%s" extends "%s", whose class hierarchy will change in %s: %s',
                    $class->getDisplayName(),
                    $parent->getDisplayName(),
                    $version,
                    $this->argument($attribute, 'description', 1) ?? '?'
                ))],
                default => [],
            }];
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateParentMethods(ClassReflection $class, ClassReflection $parent): array
    {
        $errors = [];
        $native = $class->getNativeReflection();

        foreach ($parent->getNativeReflection()->getMethods() as $parentMethod) {
            if ($parentMethod->getDeclaringClass()->getName() !== $parent->getName()) {
                continue;
            }

            $override = null;
            if ($native->hasMethod($parentMethod->getName())
                && $native->getMethod($parentMethod->getName())->getDeclaringClass()->getName() === $native->getName()) {
                $override = $native->getMethod($parentMethod->getName());
            }

            foreach ($parentMethod->getAttributes() as $attribute) {
                $errors = [...$errors, ...match ($attribute->getName()) {
                    BecomesAbstract::class => $this->validateBecomesAbstract($attribute, $class, $parentMethod, $override),
                    NewOptionalParameter::class => $this->validateNewParameter($attribute, $class, $parentMethod, $override),
                    ParameterTypeWidening::class => $this->validateWidening($attribute, $class, $parentMethod, $override),
                    ReturnTypeNarrowing::class => $this->validateReturnNarrowing($attribute, $class, $parentMethod, $override),
                    default => [],
                }];
            }
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateBecomesAbstract(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $class, \ReflectionMethod $parentMethod, ?\ReflectionMethod $override): array
    {
        if ($override !== null || $class->isAbstract()) {
            return [];
        }

        return [$this->error(\sprintf(
            '"%s::%s()" will become abstract in %s. Implement it in "%s" now to stay compatible with both versions.',
            $parentMethod->getDeclaringClass()->getShortName(),
            $parentMethod->getName(),
            $this->argument($attribute, 'version', 0) ?? '?',
            $class->getDisplayName()
        ))];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateNewParameter(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $class, \ReflectionMethod $parentMethod, ?\ReflectionMethod $override): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        if ($override === null || !\is_string($parameterName)) {
            return [];
        }

        foreach ($override->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return [];
            }
        }

        return [$this->error(\sprintf(
            '"%s::%s()" will get a new optional parameter $%s (%s) in %s. Add it to the override in "%s" now to stay compatible with both versions.',
            $parentMethod->getDeclaringClass()->getShortName(),
            $parentMethod->getName(),
            $parameterName,
            $this->argument($attribute, 'parameterType', 2) ?? '?',
            $this->argument($attribute, 'version', 0) ?? '?',
            $class->getDisplayName()
        ))];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateWidening(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $class, \ReflectionMethod $parentMethod, ?\ReflectionMethod $override): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        $newType = $this->argument($attribute, 'newType', 2);
        if ($override === null || !\is_string($parameterName) || !\is_string($newType)) {
            return [];
        }

        $announced = $this->typeResolver->resolve($newType, $parentMethod->getDeclaringClass()->getName());
        if ($announced === null) {
            return [];
        }

        $method = $class->getNativeMethod($override->getName());
        foreach ($method->getVariants()[0]->getParameters() as $parameter) {
            if ($parameter->getName() !== $parameterName) {
                continue;
            }
            if ($parameter->getType()->isSuperTypeOf($announced)->yes()) {
                return [];
            }

            return [$this->error(\sprintf(
                'Parameter $%s of "%s::%s()" will be widened to %s in %s. Widen the override in "%s" now to stay compatible with both versions.',
                $parameterName,
                $parentMethod->getDeclaringClass()->getShortName(),
                $parentMethod->getName(),
                $newType,
                $this->argument($attribute, 'version', 0) ?? '?',
                $class->getDisplayName()
            ))];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateReturnNarrowing(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $class, \ReflectionMethod $parentMethod, ?\ReflectionMethod $override): array
    {
        $newType = $this->argument($attribute, 'newType', 1);
        if ($override === null || !\is_string($newType)) {
            return [];
        }

        $announced = \in_array(\strtolower($newType), ['self', 'static', '$this'], true)
            ? new ObjectType($class->getName())
            : $this->typeResolver->resolve($newType, $parentMethod->getDeclaringClass()->getName());
        if ($announced === null) {
            return [];
        }

        $returnType = $class->getNativeMethod($override->getName())->getVariants()[0]->getReturnType();
        if ($announced->isSuperTypeOf($returnType)->yes()) {
            return [];
        }

        return [$this->error(\sprintf(
            'The return type of "%s::%s()" will be narrowed to %s in %s. Narrow the override in "%s" now to stay compatible with both versions.',
            $parentMethod->getDeclaringClass()->getShortName(),
            $parentMethod->getName(),
            $newType,
            $this->argument($attribute, 'version', 0) ?? '?',
            $class->getDisplayName()
        ))];
    }

    private function argument(ReflectionAttribute|FakeReflectionAttribute $attribute, string $name, int $position): mixed
    {
        $arguments = $attribute->getArguments();

        return $arguments[$name] ?? $arguments[$position] ?? null;
    }

    private function error(string $message): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('shopware.futureIncompatibility')
            ->build();
    }
}
