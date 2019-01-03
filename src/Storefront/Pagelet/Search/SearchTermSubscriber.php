<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchBuilder;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Shopware\Storefront\Page\Search\SearchPageRequestEvent;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;
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

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            \Shopware\Storefront\Event\SearchEvents::SEARCH_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(SearchPageRequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::TERM_PARAMETER)) {
            return;
        }

        $page = $event->getSearchPageRequest();

        $page->getSearchRequest()->setSearchTerm(
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
            $request->getSearchRequest()->getSearchTerm(),
            ProductDefinition::class,
            $event->getContext()
        );
    }
}
