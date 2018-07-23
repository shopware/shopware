<?php declare(strict_types=1);

namespace Shopware\Administration\Search;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Util\KeywordSearchTermInterpreter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Shopware\Core\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
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
     * @var KeywordSearchTermInterpreter
     */
    private $interpreter;

    public function __construct(
        ContainerInterface $container,
        KeywordSearchTermInterpreter $interpreter,
        RepositoryInterface $changesRepository
    ) {
        $this->container = $container;
        $this->changesRepository = $changesRepository;
        $this->interpreter = $interpreter;
    }

    public function search(string $term, int $limit, Context $context, string $userId): array
    {
        $results = $this->searchEntities($term, $context);

        //apply audit log for each entity, which considers which data the user working with
        $results = $this->applyAuditLog($results, $userId, $context);

        $grouped = $this->sortEntities($limit, $results);

        $results = $this->fetchEntities($context, $grouped);

        return [
            'data' => array_values($results),
        ];
    }

    /**
     * @param IdSearchResult[] $results
     * @param string           $userId
     * @param Context          $context
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

                $row['_score'] = $row['_score'] ?? 1;

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

                $entity['_score'] = $entity['_score'] ?? 1;

                $flat[] = $entity;
            }
        }

        return $flat;
    }

    private function fetchEntities(Context $context, array $grouped): array
    {
        $results = [];
        foreach ($grouped as $definition => $rows) {
            /** @var string|EntityDefinition $definition */
            $repository = $this->container->get($definition::getEntityName() . '.repository');

            $criteria = new ReadCriteria(\array_keys($rows));

            /** @var EntityCollection $entities */
            $entities = $repository->read($criteria, $context);

            foreach ($entities as $entity) {
                $score = (float) $rows[$entity->getId()];

                $entity->addExtension('search', new ArrayStruct(['_score' => $score]));

                $results[] = [
                    'type' => $definition::getEntityName(),
                    'score' => $score,
                    'entity' => $entity,
                ];
            }
        }

        return $results;
    }

    private function sortEntities(int $limit, array $results): array
    {
        //create flat result to sort all elements descending by score
        $flat = $this->createFlatResult($results);
        \usort($flat, function (array $a, array $b) {
            return $b['_score'] <=> $a['_score'];
        });

        //create internal paging for best matches
        $flat = \array_slice($flat, 0, $limit);

        //group best hits to send one read request per definition
        $grouped = [];
        foreach ($flat as $row) {
            $definition = $row['definition'];

            $grouped[$definition][$row['primary_key']] = $row['_score'];
        }

        return $grouped;
    }

    private function searchEntities(string $term, Context $context): array
    {
        $definitions = [
            ProductDefinition::class,
            OrderDefinition::class,
            CustomerDefinition::class,
        ];

        $results = [];

        //fetch best matches for defined definitions
        foreach ($definitions as $definition) {
            $results[$definition] = $this->searchEntity($term, $definition, $context);
        }

        return $results;
    }

    private function searchEntity(string $term, string $definition, Context $context): IdSearchResult
    {
        $criteria = new Criteria();
        $criteria->setLimit(15);

        /** @var string|EntityDefinition $definition */
        $pattern = $this->interpreter->interpret($term, $definition::getEntityName(), $context);

        $keywordField = $definition::getEntityName() . '.searchKeywords.keyword';
        $rankingField = $definition::getEntityName() . '.searchKeywords.ranking';
        $languageField = $definition::getEntityName() . '.searchKeywords.languageId';

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new TermQuery($keywordField, $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    $rankingField
                )
            );
        }

        $criteria->addQuery(
            new ScoreQuery(
                new MatchQuery($keywordField, $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                $rankingField
            )
        );

//        $criteria->addFilter(new TermsQuery($keywordField, array_values($pattern->getAllTerms())));
//        $criteria->addFilter(new TermQuery($languageField, $context->getLanguageId()));

        /* @var RepositoryInterface $repository */
        $repository = $this->container->get($definition::getEntityName() . '.repository');

        return $repository->searchIds($criteria, $context);
    }
}
