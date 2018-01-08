<?php declare(strict_types=1);

namespace Shopware\Administration\Search;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchTermInterpreter;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\RepositoryInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\ArrayStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuditLogSearch
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var SearchTermInterpreter
     */
    private $interpreter;

    /**
     * @var EntityScoreQueryBuilder
     */
    private $scoreBuilder;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        ContainerInterface $container,
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder,
        Connection $connection
    ) {
        $this->container = $container;
        $this->interpreter = $interpreter;
        $this->scoreBuilder = $scoreBuilder;
        $this->connection = $connection;
    }

    public function search(string $term, int $page, int $limit, TranslationContext $context, string $userId): array
    {
        $definitions = [
            ProductDefinition::class,
            OrderDefinition::class,
            CustomerDefinition::class,
        ];

        $results = [];

        $total = 0;
        foreach ($definitions as $definition) {
            $result = $this->searchDefinition($definition, $term, $context);
            $total += $result->getTotal();
            $results[$definition] = $result;
        }

        $results = $this->applyAuditLog($results, $userId);

        $flat = $this->createFlatResult($results);

        usort($flat, function (Entity $a, Entity $b) {
            $scoreA = $a->getExtension('search');
            $scoreB = $b->getExtension('search');

            /** @var ArrayStruct $scoreA */
            /** @var ArrayStruct $scoreB */
            $scoreA = $scoreA ? $scoreA->get('score') : 0;
            $scoreB = $scoreB ? $scoreB->get('score') : 0;

            return (float) $scoreB <=> (float) $scoreA;
        });

        $offset = ($page - 1) * $limit;
        $flat = array_slice($flat, $offset, $limit);

        return [
            'data' => array_values($flat),
            'total' => $total,
        ];
    }

    /**
     * @param string|EntityDefinition $definition
     * @param string                  $term
     * @param TranslationContext      $context
     *
     * @return SearchResultInterface
     */
    private function searchDefinition(string $definition, string $term, TranslationContext $context): SearchResultInterface
    {
        $repository = $this->container->get($definition::getRepositoryClass());

        $pattern = $this->interpreter->interpret($term, $context);

        $queries = $this->scoreBuilder->buildScoreQueries($pattern, $definition, $definition::getEntityName());

        $criteria = new Criteria();
        $criteria->setLimit(30);
        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        /* @var RepositoryInterface $repository */
        return $repository->search($criteria, $context);
    }

    /**
     * @param SearchResultInterface[] $results
     * @param string                  $userId
     *
     * @return array
     */
    private function applyAuditLog(array $results, string $userId): array
    {
        /** @var QueryBuilder $query */
        $query = $this->connection->createQueryBuilder();

        $query->addSelect([
            'log.entity',
            'log.foreign_key',
            'COUNT(log.user_id) as `action_count`',
        ]);
        $query->from('audit_log', 'log');
        $query->andWhere('log.user_id = :user');
        $query->setParameter('user', $userId);
        $query->addGroupBy('entity');
        $query->addGroupBy('foreign_key');
        $query->addOrderBy('action_count', 'DESC');

        $data = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

        /** @var EntityDefinition $definition */
        foreach ($results as $definition => $result) {
            if (!array_key_exists($definition, $data)) {
                continue;
            }

            $scoring = $data[$definition];

            /** @var Entity $entity */
            foreach ($result as $entity) {
                $score = $this->getEntityScore($scoring, $entity->getId());

                if (!$entity->hasExtension('search')) {
                    continue;
                }

                /** @var ArrayStruct $extension */
                $extension = $entity->getExtension('search');
                $extension->set('score', (float) $extension->get('score') * $score);
            }
        }

        return $results;
    }

    private function getEntityScore(array $scoring, string $id)
    {
        $fallback = 1.1;
        foreach ($scoring as $score) {
            if ($score['foreign_key'] === $id) {
                return 1 + ($score['action_count'] / 10);
            }
        }

        return $fallback;
    }

    /**
     * @param SearchResultInterface[] $results
     *
     * @return array
     */
    private function createFlatResult($results): array
    {
        $flat = [];
        foreach ($results as $result) {
            foreach ($result as $entity) {
                $flat[] = $entity;
            }
        }

        return $flat;
    }
}
