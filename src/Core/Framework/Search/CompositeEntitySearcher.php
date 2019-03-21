<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\RepositoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataCollection;

class CompositeEntitySearcher
{
    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    /**
     * @var string[]|EntityDefinition[]
     */
    private $definitions;
    
    /**
     * @var SearchTermInterpreter
     */
    private $interpreter;
    
    /**
     * @var EntityScoreQueryBuilder
     */
    private $scoreBuilder;

    public function __construct(
        DefinitionRegistry $definitionRegistry,
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder,
        iterable $definitions
    ) {
        $this->definitionRegistry = $definitionRegistry;

        foreach ($definitions as $definition) {
            $this->definitions[] = get_class($definition);
        }
        $this->interpreter = $interpreter;
        $this->scoreBuilder = $scoreBuilder;
    }

    public function search(string $term, int $limit, Context $context): array
    {
        $entities = [];

        foreach ($this->definitions as $definition) {
            $criteria = new Criteria();
            $criteria->setLimit($limit);
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            $pattern = $this->interpreter->interpret($term);

            $queries = $this->scoreBuilder->buildScoreQueries($pattern, $definition, $definition::getEntityName());

            $criteria->addQuery(...$queries);

            $repository = $this->definitionRegistry->getRepository($definition::getEntityName());

            $result = $repository->search($criteria, $context);

            $entities[] = [
                'type' => $definition::getEntityName(),
                'total' => $result->getTotal(),
                'entities' => $result
            ];
        }

        return $entities;
    }
}
