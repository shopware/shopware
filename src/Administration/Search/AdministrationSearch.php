<?php declare(strict_types=1);

namespace Shopware\Administration\Search;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AdministrationSearch
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
     * @var RepositoryInterface
     */
    private $changesRepository;

    public function __construct(
        ContainerInterface $container,
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder,
        RepositoryInterface $changesRepository
    ) {
        $this->container = $container;
        $this->interpreter = $interpreter;
        $this->scoreBuilder = $scoreBuilder;
        $this->changesRepository = $changesRepository;
    }

    public function search(string $term, int $page, int $limit, Context $context, string $userId): array
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

        $results = $this->applyAuditLog($results, $userId, $context);

        $flat = $this->createFlatResult($results);

        usort($flat, function (Entity $a, Entity $b) {
            $scoreA = $a->getExtension('search');
            $scoreB = $b->getExtension('search');

            /** @var ArrayStruct $scoreA */
            /** @var ArrayStruct $scoreB */
            $scoreA = $scoreA ? $scoreA->get('_score') : 0;
            $scoreB = $scoreB ? $scoreB->get('_score') : 0;

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
     * @param Context      $context
     *
     * @return SearchResultInterface
     */
    private function searchDefinition(string $definition, string $term, Context $context): SearchResultInterface
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
    private function applyAuditLog(array $results, string $userId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new TermsQuery('version_commit_data.entityName', [
                ProductDefinition::getEntityName(),
                OrderDefinition::getEntityName(),
                CustomerDefinition::getEntityName(),
            ])
        );
        $criteria->addFilter(
            new TermQuery('version_commit_data.userId', $userId)
        );

        $criteria->addSorting(new FieldSorting('version_commit_data.autoIncrement', 'DESC'));
        $criteria->setLimit(100);

        $changes = $this->changesRepository->search($criteria, $context);

        foreach ($results as $definition => $entities) {
            $definitionChanges = $changes->filterByEntity($definition);

            if ($definitionChanges->count() <= 0) {
                continue;
            }

            /** @var Entity $entity */
            foreach ($entities as $entity) {
                $entityChanges = $definitionChanges->filterByEntityPrimary($definition, ['id' => $entity->getId()]);

                $score = 1.1;
                if ($entityChanges->count() > 0) {
                    $score = 1 + ($entityChanges->count() / 10);
                }

                if (!$entity->hasExtension('search')) {
                    continue;
                }

                /** @var ArrayStruct $extension */
                $extension = $entity->getExtension('search');
                $extension->set('_score', (float) $extension->get('_score') * $score);
            }
        }

        return $results;
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
