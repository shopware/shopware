<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Log\Package;

/**
 * Opt-in future-compatibility analysis (see future-compatibility.neon): reports call
 * sites that work against the current version but break with a change announced via a
 * BC-change attribute, so callers can be made compatible with both versions ahead of
 * the next major.
 *
 * @implements Rule<CallLike>
 *
 * @internal
 */
#[Package('framework')]
class FutureCallSiteRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly AnnouncedTypeResolver $typeResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $methodName = $this->resolveMethodName($node);
        if ($methodName === null) {
            return [];
        }
        $classReflection = $this->resolveClassReflection($node, $scope, $methodName);
        if ($classReflection === null) {
            return [];
        }

        $native = $classReflection->getNativeReflection();
        $errors = $this->validateInternalClass($native->getAttributes(), $classReflection);

        if (!$native->hasMethod($methodName)) {
            return $errors;
        }
        $method = $native->getMethod($methodName);
        $symbol = \sprintf('%s::%s()', $classReflection->getDisplayName(), $methodName);

        foreach ($method->getAttributes() as $attribute) {
            $errors = [...$errors, ...match ($attribute->getName()) {
                BecomesInternal::class => [$this->error(\sprintf('"%s" will become internal in %s. Stop calling it to stay compatible.', $symbol, $this->argument($attribute, 'version', 0) ?? '?'))],
                VisibilityChange::class => $this->validateVisibility($attribute, $classReflection, $scope, $symbol),
                ParameterRemoval::class => $this->validateRemoval($attribute, $method, $node, $symbol),
                ParameterNameChange::class => $this->validateNamedArgument($attribute, $node, $symbol),
                ParameterTypeNarrowing::class => $this->validateNarrowing($attribute, $method, $node, $scope, $symbol),
                default => [],
            }];
        }

        return $errors;
    }

    private function resolveMethodName(CallLike $node): ?string
    {
        if (($node instanceof MethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier) {
            return $node->name->toString();
        }

        return $node instanceof New_ ? '__construct' : null;
    }

    private function resolveClassReflection(CallLike $node, Scope $scope, string $methodName): ?ClassReflection
    {
        if ($node instanceof MethodCall) {
            foreach ($scope->getType($node->var)->getObjectClassReflections() as $classReflection) {
                if ($classReflection->hasNativeMethod($methodName)) {
                    return $classReflection;
                }
            }

            return null;
        }

        if (($node instanceof StaticCall || $node instanceof New_) && $node->class instanceof Name) {
            $className = $scope->resolveName($node->class);

            return $this->reflectionProvider->hasClass($className)
                ? $this->reflectionProvider->getClass($className)
                : null;
        }

        return null;
    }

    /**
     * @param list<ReflectionAttribute|FakeReflectionAttribute> $classAttributes
     *
     * @return list<IdentifierRuleError>
     */
    private function validateInternalClass(array $classAttributes, ClassReflection $classReflection): array
    {
        foreach ($classAttributes as $attribute) {
            if ($attribute->getName() === BecomesInternal::class) {
                return [$this->error(\sprintf(
                    'Class "%s" will become internal in %s. Stop using it to stay compatible.',
                    $classReflection->getDisplayName(),
                    $this->argument($attribute, 'version', 0) ?? '?'
                ))];
            }
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateVisibility(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $declaring, Scope $scope, string $symbol): array
    {
        $newVisibility = $this->argument($attribute, 'newVisibility', 1);
        $caller = $scope->isInClass() ? $scope->getClassReflection() : null;

        $allowed = match ($newVisibility) {
            'protected' => $caller !== null && ($caller->getName() === $declaring->getName() || $caller->isSubclassOfClass($declaring)),
            'private' => $caller !== null && $caller->getName() === $declaring->getName(),
            default => true,
        };
        if ($allowed) {
            return [];
        }

        return [$this->error(\sprintf(
            '"%s" will become %s in %s. This call will break; stop calling it from outside that scope.',
            $symbol,
            \is_string($newVisibility) ? $newVisibility : '?',
            $this->argument($attribute, 'version', 0) ?? '?'
        ))];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateRemoval(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionMethod $method, CallLike $node, string $symbol): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        if (!\is_string($parameterName) || $this->findArgument($node, $method, $parameterName) === null) {
            return [];
        }

        return [$this->error(\sprintf(
            'Parameter $%s of "%s" will be removed in %s. Stop passing it to stay compatible with both versions.',
            $parameterName,
            $symbol,
            $this->argument($attribute, 'version', 0) ?? '?'
        ))];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateNamedArgument(ReflectionAttribute|FakeReflectionAttribute $attribute, CallLike $node, string $symbol): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        $newName = $this->argument($attribute, 'newName', 2);

        foreach ($node->getArgs() as $arg) {
            if ($arg->name !== null && \is_string($parameterName) && $arg->name->toString() === $parameterName) {
                return [$this->error(\sprintf(
                    'Parameter $%s of "%s" will be renamed to $%s in %s. A named argument cannot be compatible with both versions; pass it positionally.',
                    $parameterName,
                    $symbol,
                    \is_string($newName) ? $newName : '?',
                    $this->argument($attribute, 'version', 0) ?? '?'
                ))];
            }
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateNarrowing(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionMethod $method, CallLike $node, Scope $scope, string $symbol): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        $newType = $this->argument($attribute, 'newType', 2);
        if (!\is_string($parameterName) || !\is_string($newType)) {
            return [];
        }

        $arg = $this->findArgument($node, $method, $parameterName);
        if ($arg === null) {
            return [];
        }

        $announced = $this->typeResolver->resolve($newType, $method->getDeclaringClass()->getName());
        if ($announced === null) {
            return [];
        }

        $argType = $scope->getType($arg->value);
        if (!$announced->isSuperTypeOf($argType)->no()) {
            return [];
        }

        return [$this->error(\sprintf(
            'Parameter $%s of "%s" will be narrowed to %s in %s, but %s is passed. Pass %s to stay compatible with both versions.',
            $parameterName,
            $symbol,
            $newType,
            $this->argument($attribute, 'version', 0) ?? '?',
            $argType->describe(VerbosityLevel::typeOnly()),
            $newType
        ))];
    }

    private function findArgument(CallLike $node, \ReflectionMethod $method, string $parameterName): ?Arg
    {
        $position = null;
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                $position = $parameter->getPosition();
                break;
            }
        }

        foreach ($node->getArgs() as $index => $arg) {
            if ($arg->unpack) {
                return null;
            }
            if ($arg->name !== null ? $arg->name->toString() === $parameterName : $index === $position) {
                return $arg;
            }
        }

        return null;
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
