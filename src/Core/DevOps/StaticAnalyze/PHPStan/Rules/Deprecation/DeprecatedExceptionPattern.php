<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Removed exceptions must report the original failure before removal, without deprecation notices.
 * Construction and factories are guarded; metadata must remain safe for error formatting.
 * Existing ordinary factory deprecations may remain inside a legacy feature-flag branch.
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedExceptionPattern implements DeprecationPattern
{
    private const METADATA_METHODS = ['getStatusCode', 'getErrorCode', 'getType', 'getClass', 'getWaitTime', 'getAssignedSalesChannels'];

    public function isSupported(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation): bool
    {
        return $class->is(\Throwable::class)
            && ($isClassDeprecation || $class->getDeprecatedDescription() !== null || $method->isStatic());
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function check(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation, \Closure $methodContent): array
    {
        $calls = (new NodeFinder())->findInstanceOf($method->stmts ?? [], StaticCall::class);
        if (\in_array($method->name->toString(), self::METADATA_METHODS, true)) {
            foreach ($calls as $call) {
                if ($this->isFeatureCall($call, $scope, ['throwIfActive', 'triggerDeprecationOrThrow', 'throwException'])) {
                    return $this->error($method, $class, $isClassDeprecation, 'must remain callable without a deprecation guard');
                }
            }

            return [];
        }

        \preg_match('/tag:(v\d+\.\d+\.\d+)/', $deprecation, $matches);
        $flag = ($matches[1] ?? '') . '.0';
        $first = $method->stmts[0] ?? null;
        if ($first instanceof Expression && $first->expr instanceof StaticCall && $this->hasFlag($first->expr, $flag)) {
            if ($this->isFeatureCall($first->expr, $scope, ['throwIfActive']) || ($method->name->toString() === '__construct' && $this->isFeatureCall($first->expr, $scope, ['triggerDeprecationOrThrow']))) {
                return [];
            }
        }

        if ($method->isStatic()) {
            foreach ($calls as $call) {
                if ($this->isFeatureCall($call, $scope, ['triggerDeprecationOrThrow']) && $this->hasFlag($call, $flag)) {
                    return [];
                }
            }
        }

        return $this->error($method, $class, $isClassDeprecation, \sprintf('must start with "Feature::throwIfActive" for feature flag "%s"', $flag));
    }

    /**
     * @param list<string> $methods
     */
    private function isFeatureCall(StaticCall $call, Scope $scope, array $methods): bool
    {
        return $call->class instanceof Node\Name && $scope->resolveName($call->class) === Feature::class && $call->name instanceof Node\Identifier && \in_array($call->name->toString(), $methods, true);
    }

    private function hasFlag(StaticCall $call, string $flag): bool
    {
        return isset($call->args[0]) && $call->args[0] instanceof Node\Arg && $call->args[0]->value instanceof Node\Scalar\String_ && $call->args[0]->value->value === $flag;
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
