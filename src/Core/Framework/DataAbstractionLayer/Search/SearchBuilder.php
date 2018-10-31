<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Search\Util\KeywordSearchTermInterpreterInterface;

class SearchBuilder
{
    /**
     * @var SearchTermInterpreter
     */
    private $interpreter;

    /**
     * @var EntityScoreQueryBuilder
     */
    private $scoreBuilder;

    /**
     * @var KeywordSearchTermInterpreterInterface
     */
    private $keywordInterpreter;

    public function __construct(
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder,
        KeywordSearchTermInterpreterInterface $keywordInterpreter
    ) {
        $this->interpreter = $interpreter;
        $this->scoreBuilder = $scoreBuilder;
        $this->keywordInterpreter = $keywordInterpreter;
    }

    public function build(Criteria $criteria, string $term, string $definition, Context $context): void
    {
        /** @var string|EntityDefinition $definition */
        if (!$definition::useKeywordSearch()) {
            $pattern = $this->interpreter->interpret($term);

            $queries = $this->scoreBuilder->buildScoreQueries($pattern, $definition, $definition::getEntityName());

            $criteria->addQuery(...$queries);

            return;
        }

        $pattern = $this->keywordInterpreter->interpret($term, $definition::getEntityName(), $context);

        $keywordField = $definition::getEntityName() . '.searchKeywords.keyword';
        $rankingField = $definition::getEntityName() . '.searchKeywords.ranking';
        $languageField = $definition::getEntityName() . '.searchKeywords.languageId';

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter($keywordField, $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    $rankingField
                )
            );
        }

        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter($keywordField, $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                $rankingField
            )
        );

        $criteria->addFilter(new EqualsAnyFilter($keywordField, array_values($pattern->getAllTerms())));
        $criteria->addFilter(new EqualsFilter($languageField, $context->getLanguageId()));
    }
}
