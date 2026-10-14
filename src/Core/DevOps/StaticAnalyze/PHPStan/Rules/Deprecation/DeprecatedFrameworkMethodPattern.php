<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\ExtensionInterface;

/**
 * Checks removal behavior for deprecated classes that the framework still invokes before removal.
 * These callbacks must not emit ordinary deprecation notices during normal framework operation.
 * Discovery methods become inert in major mode, lifecycle methods remain callable, and other
 * methods on configured callback classes or services throw only once the removal flag is active.
 *
 * The owning rule filters method visibility, abstract methods, tests, and service constructors.
 * This pattern inspects the method AST, not its text or arbitrary control flow; supported guards
 * must be the first statement. It does not verify service removal from the container.
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedFrameworkMethodPattern implements DeprecationPattern
{
    /**
     * Policies are keyed by contract and method: [] and null are literal neutral return values;
     * true forbids deprecation guards so lifecycle calls remain safe, including in major mode.
     * Method policies take precedence over the throw-only class/tag policy. Supplied arrays
     * replace the defaults, and the first matching contract with a method policy wins.
     *
     * @param array<class-string, array<string, true|array{}|null>> $frameworkMethods
     * @param list<class-string> $throwOnlyClasses
     * @param list<string> $throwOnlyServiceTags
     */
    public function __construct(
        private readonly ServiceMap $serviceMap,
        private readonly array $frameworkMethods = [
            EventSubscriberInterface::class => ['getSubscribedEvents' => []],
            ExceptionHandlerInterface::class => ['matchException' => null, 'getPriority' => true],
            ExtensionInterface::class => ['getFilters' => [], 'getFunctions' => []],
            ResetInterface::class => ['reset' => true],
            Rule::class => ['getConfig' => null],
        ],
        private readonly array $throwOnlyClasses = [EventSubscriberInterface::class, ExceptionHandlerInterface::class, Rule::class],
        private readonly array $throwOnlyServiceTags = ['kernel.event_listener'],
    ) {
    }

    public function isSupported(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation): bool
    {
        // A deprecated method on an otherwise non-deprecated class still uses the ordinary rule.
        return ($isClassDeprecation || $class->getDeprecatedDescription() !== null)
            && ($this->getMethodPolicy($method, $class) !== false || $this->isThrowOnlyClass($class));
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function check(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation, \Closure $methodContent): array
    {
        $policy = $this->getMethodPolicy($method, $class);
        if ($policy === true) {
            // Reject direct Feature guards anywhere in the body, even inside conditional branches.
            // Calls into other methods are not inspected; this is not a proof that the method cannot throw.
            $calls = (new NodeFinder())->findInstanceOf($method->stmts ?? [], StaticCall::class);
            foreach ($calls as $call) {
                if ($this->isFeatureCall($call, $scope, 'triggerDeprecationOrThrow') || $this->isFeatureCall($call, $scope, 'throwIfActive') || $this->isFeatureCall($call, $scope, 'throwException')) {
                    return $this->error($method, $class, $isClassDeprecation, 'must remain callable without a deprecation guard');
                }
            }

            return [];
        }

        // Assume deprecation metadata contains a removal tag such as tag:v6.8.0.
        // Feature flags use one additional version component: v6.8.0.0.
        \preg_match('/tag:(v\d+\.\d+\.\d+)/', $deprecation, $matches);
        $flag = ($matches[1] ?? '') . '.0';
        $firstStatement = $method->stmts[0] ?? null;

        if ($policy !== false) {
            // An unconditional neutral return is also valid for an already inert implementation.
            if ($this->returnsNeutralValue($firstStatement, $policy)) {
                return [];
            }

            // Require an immediate literal return; do not accept work before discovery is disabled.
            if ($firstStatement instanceof If_ && $this->checksFlag($firstStatement->cond, $scope, $flag) && \count($firstStatement->stmts) === 1 && $this->returnsNeutralValue($firstStatement->stmts[0], $policy)) {
                return [];
            }

            return $this->error($method, $class, $isClassDeprecation, \sprintf('must return %s when feature flag "%s" is active', $policy === null ? 'null' : '[]', $flag));
        }

        // Other callback methods stay silent before removal, but must fail before any work in major mode.
        if ($firstStatement instanceof Expression && $firstStatement->expr instanceof StaticCall && $this->isFeatureCall($firstStatement->expr, $scope, 'throwIfActive') && $this->hasFlagArgument($firstStatement->expr, $flag)) {
            return [];
        }

        return $this->error($method, $class, $isClassDeprecation, \sprintf('must start with "Feature::throwIfActive" for feature flag "%s"', $flag));
    }

    /**
     * @return true|array{}|false|null false means the method has no configured policy.
     */
    private function getMethodPolicy(ClassMethod $method, ClassReflection $class): array|bool|null
    {
        foreach ($this->frameworkMethods as $contract => $methods) {
            // isset() would lose explicit null policies; false is reserved for "not configured".
            if ($class->is($contract) && \array_key_exists($method->name->toString(), $methods)) {
                return $methods[$method->name->toString()];
            }
        }

        return false;
    }

    private function isThrowOnlyClass(ClassReflection $class): bool
    {
        foreach ($this->throwOnlyClasses as $contract) {
            if ($class->is($contract)) {
                return true;
            }
        }

        // Listener detection depends on the analyzed container exposing the service under its class name.
        foreach ($this->serviceMap->getService($class->getName())?->getTags() ?? [] as $tag) {
            /** @phpstan-ignore phpstanApi.method */
            if (\in_array($tag->getName(), $this->throwOnlyServiceTags, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{}|null $policy
     */
    private function returnsNeutralValue(?Node $statement, ?array $policy): bool
    {
        if (!$statement instanceof Return_) {
            return false;
        }

        return $policy === []
            ? $statement->expr instanceof Array_ && $statement->expr->items === []
            : $statement->expr instanceof ConstFetch && strtolower($statement->expr->name->toString()) === 'null';
    }

    private function checksFlag(Node $condition, Scope $scope, string $flag): bool
    {
        // Future removal flags may not be registered yet. Accept has(flag) && isActive(flag),
        // assuming the flag is registered when that removal version becomes available.
        if ($condition instanceof BooleanAnd && $condition->left instanceof StaticCall && $this->isFeatureCall($condition->left, $scope, 'has') && $this->hasFlagArgument($condition->left, $flag)) {
            return $this->checksFlag($condition->right, $scope, $flag);
        }

        if ($condition instanceof BooleanOr) {
            // Additional flags may disable discovery earlier; the removal flag must still do so alone.
            return $this->checksFlag($condition->left, $scope, $flag) || $this->checksFlag($condition->right, $scope, $flag);
        }

        return $condition instanceof StaticCall && $this->isFeatureCall($condition, $scope, 'isActive') && $this->hasFlagArgument($condition, $flag);
    }

    private function isFeatureCall(StaticCall $call, Scope $scope, string $method): bool
    {
        return $call->class instanceof Node\Name && $scope->resolveName($call->class) === Feature::class && $call->name instanceof Identifier && $call->name->toString() === $method;
    }

    private function hasFlagArgument(StaticCall $call, string $flag): bool
    {
        // Only a literal flag in the first argument is recognized, not variables or computed expressions.
        return isset($call->args[0]) && $call->args[0] instanceof Arg && $call->args[0]->value instanceof Node\Scalar\String_ && $call->args[0]->value->value === $flag;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function error(ClassMethod $method, ClassReflection $class, bool $isClassDeprecation, string $requirement): array
    {
        return [RuleErrorBuilder::message(\sprintf('Deprecated framework method "%s::%s" %s.', $class->getName(), $method->name->toString(), $requirement))
            ->identifier($isClassDeprecation ? 'shopware.deprecatedClass' : 'shopware.deprecatedMethod')
            ->build()];
    }
}
