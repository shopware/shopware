<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * Recognises the calls that hit the database and the loop shapes that already handle a whole set of records, shared by
 * {@see NoQueryInLoopRule} and {@see QueryInLoopCollector} so that both judge a call the same way.
 *
 * @phpstan-import-type LoopContext from LoopContextVisitor
 *
 * @internal
 */
#[Package('framework')]
final class QueryCallDetector
{
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

    /**
     * Namespaces whose loops are not worth reporting:
     *
     * - a migration runs once, and one that already ran on a shop cannot be changed anyway,
     * - demodata generators only ever run against a development shop,
     * - the DAL itself is the batching layer, so its own loops over definitions and associations are how the
     *   batching is built rather than an instance of the problem.
     *
     * The DAL entry is deliberately anchored on the framework namespace: a `DataAbstractionLayer` namespace inside a
     * domain, such as the product indexers, is ordinary code.
     *
     * @var list<string>
     */
    private const EXCLUDED_NAMESPACES = [
        '\\Migration\\',
        '\\Demodata\\',
        'Shopware\\Core\\Framework\\DataAbstractionLayer\\',
    ];

    public function isExcludedClass(Scope $scope): bool
    {
        if (!$scope->isInClass()) {
            return true;
        }

        $class = $scope->getClassReflection()->getName();

        foreach (self::EXCLUDED_NAMESPACES as $namespace) {
            if (str_contains($class, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The class declaring the queried method, or null when the call does not hit the database.
     *
     * @return class-string|null
     */
    public function getQueryClass(string $method, Type $calledOn): ?string
    {
        foreach (self::QUERY_METHODS as $class => $methods) {
            if (\in_array($method, $methods, true) && (new ObjectType($class))->isSuperTypeOf($calledOn)->yes()) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param list<LoopContext> $loops
     */
    public function scalesWithRecords(array $loops, Scope $scope): bool
    {
        foreach ($loops as $loop) {
            if (!$this->isBatchedLoop($loop, $scope)) {
                return true;
            }
        }

        return false;
    }

    public function shortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
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
}
