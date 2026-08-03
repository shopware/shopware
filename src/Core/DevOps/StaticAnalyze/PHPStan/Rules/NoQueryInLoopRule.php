<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * Detects the N+1 query problem: a database query that is executed once per loop iteration instead of loading the
 * data for all iterations up front.
 *
 * Which calls are inside a loop is provided by {@see LoopContextVisitor}, which has to be registered as
 * `phpstan.parser.richParserNodeVisitor`.
 *
 * @phpstan-import-type LoopContext from LoopContextVisitor
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoQueryInLoopRule implements Rule
{
    use InTestClassTrait;

    /**
     * Methods that hit the database, grouped by the class they are declared on. Only calls that can be batched are
     * listed: the DBAL read methods (a single query with an `IN (...)` condition replaces all of them) and the DAL
     * repository methods (they all take a payload or criteria covering many records at once).
     *
     * DBAL write methods are intentionally missing: writing record by record through a prepared statement is an
     * established pattern in the indexers and cannot be collapsed into a single statement in general.
     *
     * @var array<class-string, list<string>>
     */
    private const QUERY_METHODS = [
        Connection::class => [
            'executeQuery',
            'executeCacheQuery',
            'fetchAllAssociative',
            'fetchAllAssociativeIndexed',
            'fetchAllKeyValue',
            'fetchAllNumeric',
            'fetchAssociative',
            'fetchFirstColumn',
            'fetchNumeric',
            'fetchOne',
            'iterateAssociative',
            'iterateAssociativeIndexed',
            'iterateColumn',
            'iterateKeyValue',
            'iterateNumeric',
        ],
        QueryBuilder::class => [
            'executeQuery',
            'fetchAllAssociative',
            'fetchAllAssociativeIndexed',
            'fetchAllKeyValue',
            'fetchAllNumeric',
            'fetchAssociative',
            'fetchFirstColumn',
            'fetchNumeric',
            'fetchOne',
        ],
        EntityRepository::class => [
            'search',
            'searchIds',
            'aggregate',
            'create',
            'update',
            'upsert',
            'delete',
        ],
        SalesChannelRepository::class => [
            'search',
            'searchIds',
            'aggregate',
        ],
    ];

    /**
     * Loops driven by one of these hand out a whole chunk of records per iteration, so a single query in the loop
     * body is the batched solution and not an N+1 problem.
     *
     * @var list<class-string>
     */
    private const CHUNK_ITERATORS = [
        IterableQuery::class,
        RepositoryIterator::class,
        SalesChannelRepositoryIterator::class,
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        /** @var list<LoopContext> $loops */
        $loops = $node->getAttribute(LoopContextVisitor::ATTRIBUTE, []);

        if ($loops === [] || $this->isInTestClass($scope)) {
            return [];
        }

        $queryClass = $this->getQueryClass($node->name->toString(), $scope->getType($node->var));

        if ($queryClass === null) {
            return [];
        }

        // the call only scales with the number of records if at least one enclosing loop does
        foreach ($loops as $loop) {
            if ($this->isBatchedLoop($loop, $scope)) {
                continue;
            }

            return [
                RuleErrorBuilder::message(\sprintf(
                    '%s::%s() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                    $this->getShortClassName($queryClass),
                    $node->name->toString()
                ))
                    ->identifier('shopware.queryInLoop')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * @return class-string|null
     */
    private function getQueryClass(string $method, Type $calledOn): ?string
    {
        foreach (self::QUERY_METHODS as $class => $methods) {
            if (\in_array($method, $methods, true) && (new ObjectType($class))->isSuperTypeOf($calledOn)->yes()) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param LoopContext $loop
     */
    private function isBatchedLoop(array $loop, Scope $scope): bool
    {
        if ($loop['bounded'] || $loop['drain']) {
            return true;
        }

        return $this->iteratesChunks($loop, $scope) || $this->bindsBatchOfValues($loop, $scope);
    }

    /**
     * `while ($ids = $iterator->fetch())` hands out a whole chunk per iteration when the iterator is one of the
     * paginating DAL helpers.
     *
     * @param LoopContext $loop
     */
    private function iteratesChunks(array $loop, Scope $scope): bool
    {
        $producer = $loop['chunkProducer'];

        if (!$producer instanceof MethodCall) {
            return false;
        }

        $producedBy = $scope->getType($producer->var);

        foreach (self::CHUNK_ITERATORS as $class) {
            if ((new ObjectType($class))->isSuperTypeOf($producedBy)->yes()) {
                return true;
            }
        }

        return false;
    }

    /**
     * A `foreach` that binds a list per iteration walks batches of records, not single records — for example
     * `foreach ($chunks as $chunk)` or `foreach ($rows as ['ids' => $ids])`. A record represented as a map, such as
     * `foreach ($rows as $row)`, is not a batch.
     *
     * @param LoopContext $loop
     */
    private function bindsBatchOfValues(array $loop, Scope $scope): bool
    {
        foreach ($loop['batchVariables'] as $name) {
            if ($scope->hasVariableType($name)->yes() && $scope->getVariableType($name)->isList()->yes()) {
                return true;
            }
        }

        return false;
    }

    private function getShortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
