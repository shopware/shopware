<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search;

use Shopware\Core\Framework\Search\Util\KeywordSearchTermInterpreter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Storefront\Subscriber\SearchTermSubscriber;

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
     * @var KeywordSearchTermInterpreter
     */
    private $keywordInterpreter;

    public function __construct(
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder,
        KeywordSearchTermInterpreter $keywordInterpreter
    ) {
        $this->interpreter = $interpreter;
        $this->scoreBuilder = $scoreBuilder;
        $this->keywordInterpreter = $keywordInterpreter;
    }

    public function build(string $term, string $definition, Context $context): Criteria
    {
        $pattern = $this->interpreter->interpret($term, $context);

        /** @var string|EntityDefinition $definition */
        $queries = $this->scoreBuilder->buildScoreQueries($pattern, $definition, $definition::getEntityName());

        $criteria = new Criteria();
        $criteria->setLimit(5);

        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        return $criteria;
    }

    private function keywordSearch($term, Context $context): Criteria
    {
        $pattern = $this->keywordInterpreter->interpret($term, $context);

        $criteria = new Criteria();
        $criteria->setLimit(5);

        $queries = [];

        foreach ($pattern->getTerms() as $termPattern) {
            $query = new TermQuery(SearchTermSubscriber::KEYWORD_FIELD, $termPattern->getTerm());
            $queries[] = new ScoreQuery($query, $termPattern->getScore(), SearchTermSubscriber::BOOSTING_FIELD);
        }

        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        $criteria->addFilter(
            new TermsQuery(SearchTermSubscriber::KEYWORD_FIELD, array_values($pattern->getAllTerms()))
        );

        $criteria->addFilter(
            new TermQuery(SearchTermSubscriber::LANGUAGE_FIELD, Defaults::LANGUAGE)
        );

        return $criteria;
    }
}
