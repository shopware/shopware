<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Search\Util\KeywordSearchTermInterpreter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageRequestEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchTermSubscriber implements EventSubscriberInterface
{
    public const KEYWORD_FIELD = 'product.searchKeywords.keyword';
    public const BOOSTING_FIELD = 'product.searchKeywords.ranking';
    public const LANGUAGE_FIELD = 'product.searchKeywords.languageId';
    public const TERM_PARAMETER = 'search';

    /**
     * @var \Shopware\Core\Framework\Search\Util\KeywordSearchTermInterpreter
     */
    private $interpreter;

    public function __construct(KeywordSearchTermInterpreter $interpreter)
    {
        $this->interpreter = $interpreter;
    }

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::TERM_PARAMETER)) {
            return;
        }

        $page = $event->getListingPageRequest();
        if (!$page instanceof SearchPageRequest) {
            return;
        }

        $page->setSearchTerm(
            trim((string) $request->query->get(self::TERM_PARAMETER))
        );
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->getRequest() instanceof SearchPageRequest) {
            return;
        }

        /** @var SearchPageRequest $request */
        $term = $request->getSearchTerm();

        $pattern = $this->interpreter->interpret($term, ProductDefinition::getEntityName(), $event->getContext());

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
