<?php declare(strict_types=1);

namespace Shopware\Administration\Search;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\ORM\Search\SearchBuilder;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AdministrationSearch
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $changesRepository;

    /**
     * @var SearchBuilder
     */
    private $searchBuilder;

    public function __construct(
        ContainerInterface $container,
        SearchBuilder $searchBuilder,
        RepositoryInterface $changesRepository
    ) {
        $this->container = $container;
        $this->changesRepository = $changesRepository;
        $this->searchBuilder = $searchBuilder;
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
        //fetch best matches for defined definitions
        foreach ($definitions as $definition) {
            $result = $this->searchDefinition($definition, $term, $context);
            $total += $result->getTotal();
            $results[$definition] = $result;
        }

        //apply audit log for each entity, which considers which data the user working with
        $results = $this->applyAuditLog($results, $userId, $context);

        //create flat result to sort all elements descending by score
        $flat = $this->createFlatResult($results);
        usort($flat, function (array $a, array $b) {
            return $b['_score'] <=> $a['_score'];
        });

        //create internal paging for best matches
        $offset = ($page - 1) * $limit;
        $flat = array_slice($flat, $offset, $limit);

        //group best hits to send one read request per definition
        $grouped = [];
        foreach ($flat as $row) {
            $definition = $row['definition'];
            $grouped[$definition][$row['primary_key']] = $row['_score'];
        }

        $results = [];
        foreach ($grouped as $definition => $rows) {
            /** @var string|EntityDefinition $definition */
            $repository = $this->container->get($definition::getEntityName() . '.repository');

            /** @var EntityCollection $entities */
            $entities = $repository->read(new ReadCriteria(array_keys($rows)), $context);

            foreach ($entities as $entity) {
                $entity->addExtension(
                    'search',
                    new ArrayStruct(['_score' => $rows[$entity->getId()]])
                );

                $results[] = $entity;
            }
        }

        return [
            'data' => array_values($results),
            'total' => $total,
        ];
    }

    /**
     * @param string|EntityDefinition $definition
     * @param string                  $term
     * @param Context                 $context
     *
     * @return EntitySearchResult
     */
    private function searchDefinition(string $definition, string $term, Context $context): IdSearchResult
    {
        $repository = $this->container->get($definition::getEntityName() . '.repository');

        $criteria = $this->searchBuilder->build($term, $definition, $context);

        /* @var RepositoryInterface $repository */
        return $repository->searchIds($criteria, $context);
    }

    /**
     * @param IdSearchResult[] $results
     * @param string           $userId
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
        $criteria->setLimit(40);

        /** @var VersionCommitDataCollection $changes */
        $changes = $this->changesRepository->search($criteria, $context)->getEntities();

        foreach ($results as $definition => $entities) {
            $definitionChanges = $changes->filterByEntity($definition);

            if ($definitionChanges->count() <= 0) {
                continue;
            }

            foreach ($entities->getData() as &$row) {
                $id = $row['primary_key'];

                $entityChanges = $definitionChanges->filterByEntityPrimary($definition, ['id' => $id]);

                $score = 1.1;
                if ($entityChanges->count() > 0) {
                    $score = 1 + ($entityChanges->count() / 10);
                }

                $row['_score'] *= $score;
            }
        }

        return $results;
    }

    /**
     * @param IdSearchResult[] $results
     *
     * @return array
     */
    private function createFlatResult($results): array
    {
        $flat = [];
        foreach ($results as $definition => $result) {
            foreach ($result->getData() as $entity) {
                $entity['definition'] = $definition;
                $flat[] = $entity;
            }
        }

        return $flat;
    }
}
