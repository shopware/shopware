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
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedMethodsThrowDeprecationRule implements Rule
{
    /**
     * There are some exceptions to this rule, where deprecated methods should not throw a deprecation notice.
     * This is mainly the reason if the deprecated code is still called from inside the core due to BC reasons.
     */
    private const RULE_EXCEPTIONS = [
        // Subscribers still need to be called for BC reasons, therefore they do not trigger deprecations.
        'reason:remove-subscriber',
        // Entities still need to be present in the DI container, therefore they do not trigger deprecations.
        'reason:remove-entity',
        // Exception still need to be called for BC reasons, therefore they do not trigger deprecations.
        'reason:remove-exception',
        // Rules still need to be called for rule evaluation, therefore they do not trigger deprecations.
        'reason:remove-rule',
    ];

    public function __construct(private readonly ServiceMap $serviceMap)
    {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!($node->isPublic() || $node->isProtected()) || $node->isAbstract()) {
            return [];
        }

        if (!$scope->isInClass()) {
            return [];
        }

        $class = $scope->getClassReflection();

        if ($class->isInterface() || $this->isTestClass($class)) {
            return [];
        }

        $method = $class->getMethod($node->name->name, $scope);

        // reading the method content requires file I/O, so only do it when a deprecation is present
        $methodContent = fn (): string => $this->getMethodContent($node, $scope, $class);

        $classDeprecation = $class->getDeprecatedDescription();
        $methodDeprecation = $method->getDeprecatedDescription() ?? '';

        $isServiceDecorator = $this->isServiceDecorator($class);
        $handlesClassDeprecation = $this->handlesDeprecationCorrectly($classDeprecation ?? '', $methodContent)
            || ($isServiceDecorator && ($methodDeprecation !== '' || $this->delegatesToInnerWhenFeatureFlagIsActive($node, $scope, $classDeprecation ?? '')));

        if ($classDeprecation && !$this->isServiceConstructor($node, $class) && !$handlesClassDeprecation) {
            return [
                RuleErrorBuilder::message($isServiceDecorator
                    ? \sprintf(
                        'Class decorator "%s" is marked as deprecated, but method "%s" does not directly delegate to its inner service when feature flag "%s" is active.',
                        $class->getName(),
                        $method->getName(),
                        $this->getFeatureFlag($classDeprecation),
                    )
                    : \sprintf(
                        'Class "%s" is marked as deprecated, but method "%s" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.',
                        $class->getName(),
                        $method->getName()
                    ))
                    ->identifier('shopware.deprecatedClass')
                    ->build(),
            ];
        }

        // by default deprecations from parent methods are also available on all implementing methods
        // we will copy the deprecation to the implementing method, if they also have an affect there
        $deprecationOfParentMethod = !str_contains($method->getDocComment() ?? '', $methodDeprecation) && !str_contains($method->getDocComment() ?? '', 'inheritdoc');

        if (!$deprecationOfParentMethod && $methodDeprecation && !$this->handlesDeprecationCorrectly($methodDeprecation, $methodContent)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Method "%s" of class "%s" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                    $method->getName(),
                    $class->getName()
                ))
                    ->identifier('shopware.deprecatedMethod')
                    ->build(),
            ];
        }

        return [];
    }

    private function getMethodContent(Node $node, Scope $scope, ClassReflection $class): string
    {
        $filename = $class->getFileName();

        $trait = $scope->getTraitReflection();
        if ($trait) {
            $filename = $trait->getFileName();
        }

        if (!\is_string($filename)) {
            return '';
        }

        $file = new \SplFileObject($filename);
        $file->seek($node->getStartLine() - 1);

        $content = '';
        for ($i = 0; $i <= ($node->getEndLine() - $node->getStartLine()); ++$i) {
            $content .= $file->current();
            $file->next();
        }

        return $content;
    }

    /**
     * @param \Closure(): string $methodContent
     */
    private function handlesDeprecationCorrectly(string $deprecation, \Closure $methodContent): bool
    {
        foreach (self::RULE_EXCEPTIONS as $exception) {
            if (\str_contains($deprecation, $exception)) {
                return true;
            }
        }

        return \str_contains($methodContent(), 'Feature::triggerDeprecationOrThrow(');
    }

    private function isTestClass(ClassReflection $class): bool
    {
        $namespace = $class->getName();

        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if (\str_contains($namespace, '\\Tests\\')) {
            return true;
        }

        foreach ($class->getParents() as $parentClass) {
            if ($parentClass->getName() === TestCase::class) {
                return true;
            }
        }

        return false;
    }

    private function isServiceConstructor(ClassMethod $node, ClassReflection $class): bool
    {
        return $node->name->toString() === '__construct'
            && $this->serviceMap->getService($class->getName()) !== null;
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

    private function delegatesToInnerWhenFeatureFlagIsActive(ClassMethod $method, Scope $scope, string $deprecation): bool
    {
        $firstStatement = $method->stmts[0] ?? null;
        if (!$firstStatement instanceof If_ || !$this->isFeatureFlagCheck($firstStatement, $scope, $deprecation)) {
            return false;
        }

        $statements = $firstStatement->stmts;
        if (\count($statements) !== 1) {
            return false;
        }

        $statement = $statements[0];
        $call = null;
        if ($statement instanceof Return_) {
            $call = $statement->expr;
        } elseif ($statement instanceof Expression && $method->returnType instanceof Identifier && $method->returnType->toString() === 'void' && isset($method->stmts[1]) && $method->stmts[1] instanceof Return_ && $method->stmts[1]->expr === null) {
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
            && $call->var->var->name === 'this'
            && $call->var->name instanceof Identifier
            && $call->var->name->toString() === 'inner';
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
