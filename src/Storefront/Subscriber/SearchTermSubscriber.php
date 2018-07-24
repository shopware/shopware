<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\ORM\Search\SearchBuilder;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageRequestEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchTermSubscriber implements EventSubscriberInterface
{
    public const TERM_PARAMETER = 'search';

    /**
     * @var SearchBuilder
     */
    private $searchBuilder;

    public function __construct(SearchBuilder $searchBuilder)
    {
        $this->searchBuilder = $searchBuilder;
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
        /** @var SearchPageRequest $request */
        $request = $event->getRequest();

        if (!$event->getRequest() instanceof SearchPageRequest) {
            return;
        }

        $this->searchBuilder->build(
            $event->getCriteria(),
            $request->getSearchTerm(),
            ProductDefinition::class,
            $event->getContext()
        );
    }
}
