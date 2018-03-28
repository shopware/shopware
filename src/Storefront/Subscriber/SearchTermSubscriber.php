<?php

namespace Shopware\Storefront\Subscriber;

use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Defaults;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\StorefrontApi\Search\KeywordSearchTermInterpreter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchTermSubscriber implements EventSubscriberInterface
{
    public const KEYWORD_FIELD = 'product.searchKeywords.keyword';
    public const BOOSTING_FIELD = 'product.searchKeywords.ranking';
    public const LANGUAGE_FIELD = 'product.searchKeywords.languageId';
    public const TERM_PARAMETER = 'search';

    /**
     * @var KeywordSearchTermInterpreter
     */
    private $interpreter;

    public function __construct(KeywordSearchTermInterpreter $interpreter)
    {
        $this->interpreter = $interpreter;
    }

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT => 'buildCriteria'
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::TERM_PARAMETER)) {
            return;
        }

        $term = trim((string) $request->query->get(self::TERM_PARAMETER));

        $pattern = $this->interpreter->interpret($term, $event->getContext());

        $queries = [];
        foreach ($pattern->getTerms() as $term) {
            $query = new TermQuery(self::KEYWORD_FIELD, $term->getTerm());
            $queries[] = new ScoreQuery($query, $term->getScore(), self::BOOSTING_FIELD);
        }

        foreach ($queries as $query) {
            $event->getCriteria()->addQuery($query);
        }

        $event->getCriteria()->addFilter(
            new TermsQuery(self::KEYWORD_FIELD, array_values($pattern->getAllTerms()))
        );

        $event->getCriteria()->addFilter(
            new TermQuery(self::LANGUAGE_FIELD, Defaults::LANGUAGE)
        );
    }
}