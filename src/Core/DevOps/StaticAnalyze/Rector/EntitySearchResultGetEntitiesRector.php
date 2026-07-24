<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * Rewrites usages of the EntitySearchResult methods that are deprecated for v6.8.0 to the
 * getEntities() delegation their deprecation messages prescribe. Under the v6.8 feature flags
 * the deprecated methods throw instead of logging, so a usage that passes the regular pipelines
 * still breaks the nightly major run; this rule surfaces the rewrite as a pull-request-time diff.
 *
 * @internal
 */
#[Package('framework')]
final class EntitySearchResultGetEntitiesRector extends AbstractRector
{
    /**
     * Methods deprecated with a "Use getEntities()->x() instead" replacement. Deprecated methods
     * without such a replacement (add(), clear(), setEntity(), ...) have no mechanical rewrite
     * and stay out of scope.
     */
    private const DELEGATED_METHODS = [
        'count',
        'fill',
        'filter',
        'filterAndReduceByProperty',
        'filterByProperty',
        'filterInstance',
        'first',
        'firstWhere',
        'flatMap',
        'fmap',
        'get',
        'getAt',
        'getCustomFieldsValue',
        'getCustomFieldsValues',
        'getElements',
        'getIds',
        'getKeys',
        'getList',
        'has',
        'insert',
        'isEmpty',
        'last',
        'map',
        'merge',
        'reduce',
        'remove',
        'set',
        'setCustomFields',
        'slice',
        'sort',
        'sortByIdArray',
    ];

    /**
     * Functions that consume the deprecated Countable/IteratorAggregate implementations
     * through their first argument.
     */
    private const DELEGATED_FUNCTIONS = [
        'count',
        'iterator_count',
        'iterator_to_array',
        'sizeof',
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class, Foreach_::class, FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        return match (true) {
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $this->refactorMethodCall($node),
            $node instanceof Foreach_ => $this->refactorForeach($node),
            $node instanceof FuncCall => $this->refactorFuncCall($node),
            default => null,
        };
    }

    private function refactorMethodCall(MethodCall|NullsafeMethodCall $call): ?MethodCall
    {
        if (!$call->name instanceof Identifier || !\in_array($call->name->toString(), self::DELEGATED_METHODS, true)) {
            return null;
        }

        if (!$this->isSearchResult($call->var)) {
            return null;
        }

        // keep the original nullsafe short-circuit: $result?->first() becomes $result?->getEntities()->first()
        $getEntities = $call instanceof NullsafeMethodCall
            ? new NullsafeMethodCall($call->var, 'getEntities')
            : new MethodCall($call->var, 'getEntities');

        return new MethodCall($getEntities, $call->name, $call->args);
    }

    private function refactorForeach(Foreach_ $foreach): ?Foreach_
    {
        // iterating the result wrapper goes through the deprecated getIterator()
        if (!$this->isSearchResult($foreach->expr)) {
            return null;
        }

        $foreach->expr = new MethodCall($foreach->expr, 'getEntities');

        return $foreach;
    }

    private function refactorFuncCall(FuncCall $call): ?FuncCall
    {
        if (!$call->name instanceof Name || !\in_array($call->name->toLowerString(), self::DELEGATED_FUNCTIONS, true)) {
            return null;
        }

        $firstArg = $call->args[0] ?? null;
        if (!$firstArg instanceof Arg || !$this->isSearchResult($firstArg->value)) {
            return null;
        }

        $firstArg->value = new MethodCall($firstArg->value, 'getEntities');

        return $call;
    }

    private function isSearchResult(Node $node): bool
    {
        return $this->isObjectType($node, new ObjectType(EntitySearchResult::class));
    }
}
