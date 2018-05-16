<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Defaults;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Event\TransformListingPageRequestEvent;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Shopware\Content\Product\Util\KeywordSearchTermInterpreter;
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
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT => 'buildCriteria',
            ListingEvents::TRANSFORM_LISTING_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(TransformListingPageRequestEvent $event)
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
