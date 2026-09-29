<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class DeprecatedServiceDecoratorPattern implements DeprecationPattern
{
    public function __construct(
        private readonly ServiceMap $serviceMap,
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function isSupported(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation): bool
    {
        return $isClassDeprecation && $this->isServiceDecorator($class);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function check(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation, \Closure $methodContent): array
    {
        if ($method->name->toString() === 'getDecorated' || ($method->stmts !== null && $this->delegatesToInner($method, $method->stmts)) || $this->delegatesToInnerWhenFeatureFlagIsActive($method, $scope, $deprecation)) {
            return [];
        }

        if ($class->getMethod($method->name->toString(), $scope)->getDeprecatedDescription() !== null) {
            return [];
        }

        if (!$this->isMethodOnDecoratedService($method, $class)) {
            if (\str_contains($methodContent(), 'Feature::triggerDeprecationOrThrow(')) {
                return [];
            }

            return [
                RuleErrorBuilder::message(\sprintf(
                    'Class decorator "%s" is marked as deprecated, but method "%s" does not call "Feature::triggerDeprecationOrThrow". Methods not declared by the decorated service need to trigger a deprecation warning.',
                    $class->getName(),
                    $method->name->toString(),
                ))
                    ->identifier('shopware.deprecatedClass')
                    ->build(),
            ];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Class decorator "%s" is marked as deprecated, but method "%s" does not directly delegate to its inner service when feature flag "%s" is active.',
                $class->getName(),
                $method->name->toString(),
                $this->getFeatureFlag($deprecation),
            ))
                ->identifier('shopware.deprecatedClass')
                ->build(),
        ];
    }

    private function isServiceDecorator(ClassReflection $class): bool
    {
        $service = $this->serviceMap->getService($class->getName());

        if ($service === null) {
            return false;
        }

        foreach ($service->getTags() as $tag) {
            /** @phpstan-ignore phpstanApi.method */
            if ($tag->getName() === 'container.decorator') {
                return true;
            }
        }

        return false;
    }

    private function isMethodOnDecoratedService(ClassMethod $method, ClassReflection $class): bool
    {
        $service = $this->serviceMap->getService($class->getName());
        if ($service === null) {
            return true;
        }

        foreach ($service->getTags() as $tag) {
            /** @phpstan-ignore phpstanApi.method */
            if ($tag->getName() !== 'container.decorator') {
                continue;
            }

            /** @phpstan-ignore phpstanApi.method */
            $decoratedServiceId = $tag->getAttributes()['id'] ?? null;
            $decoratedClass = \is_string($decoratedServiceId) ? $this->serviceMap->getService($decoratedServiceId)?->getClass() : null;

            return \is_string($decoratedClass)
                && $this->reflectionProvider->hasClass($decoratedClass)
                && $this->reflectionProvider->getClass($decoratedClass)->hasMethod($method->name->toString());
        }

        return true;
    }

    private function delegatesToInnerWhenFeatureFlagIsActive(ClassMethod $method, Scope $scope, string $deprecation): bool
    {
        $firstStatement = $method->stmts[0] ?? null;
        if (!$firstStatement instanceof If_ || !$this->isFeatureFlagCheck($firstStatement, $scope, $deprecation)) {
            return false;
        }

        return $this->delegatesToInner($method, $firstStatement->stmts);
    }

    /**
     * @param array<Node\Stmt> $statements
     */
    private function delegatesToInner(ClassMethod $method, array $statements): bool
    {
        if ($statements === []) {
            return false;
        }

        $statement = $statements[0];
        $call = null;
        if (\count($statements) === 1 && $statement instanceof Return_) {
            $call = $statement->expr;
        } elseif (\count($statements) === 2 && $statement instanceof Expression && $method->returnType instanceof Identifier && $method->returnType->toString() === 'void' && $statements[1] instanceof Return_ && $statements[1]->expr === null) {
            $call = $statement->expr;
        }

        return $call instanceof MethodCall
            && $call->name instanceof Identifier
            && $call->name->toString() === $method->name->toString()
            && $this->isInnerServiceCall($call)
            && $this->forwardsMethodParameters($call->args, $method);
    }

    private function isFeatureFlagCheck(If_ $node, Scope $scope, string $deprecation): bool
    {
        $condition = $node->cond;

        return $condition instanceof StaticCall
            && $condition->class instanceof Node\Name
            && $scope->resolveName($condition->class) === Feature::class
            && $condition->name instanceof Identifier
            && $condition->name->toString() === 'isActive'
            && isset($condition->args[0])
            && $condition->args[0] instanceof Arg
            && $condition->args[0]->value instanceof Node\Scalar\String_
            && $condition->args[0]->value->value === $this->getFeatureFlag($deprecation);
    }

    private function isInnerServiceCall(MethodCall $call): bool
    {
        if ($call->var instanceof MethodCall) {
            return $call->var->var instanceof Variable
                && $call->var->var->name === 'this'
                && $call->var->name instanceof Identifier
                && $call->var->name->toString() === 'getDecorated';
        }

        return $call->var instanceof PropertyFetch
            && $call->var->var instanceof Variable
            && $call->var->var->name === 'this';
    }

    /**
     * @param array<Arg|Node\ArgPlaceholder|Node\VariadicPlaceholder> $arguments
     */
    private function forwardsMethodParameters(array $arguments, ClassMethod $method): bool
    {
        $arguments = \array_values($arguments);
        if (\count($arguments) !== \count($method->params)) {
            return false;
        }

        foreach ($arguments as $index => $argument) {
            $parameter = $method->params[$index];
            if (!$argument instanceof Arg || !$argument->value instanceof Variable || !$parameter->var instanceof Variable || $argument->value->name !== $parameter->var->name) {
                return false;
            }
        }

        return true;
    }

    private function getFeatureFlag(string $deprecation): string
    {
        \preg_match('/tag:(v\d+\.\d+\.\d+)/', $deprecation, $matches);

        return ($matches[1] ?? '') . '.0';
    }
}
