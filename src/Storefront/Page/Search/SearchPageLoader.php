<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGatewayInterface;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var ProductSearchGatewayInterface
     */
    private $searchGateway;

    public function __construct(
        GenericPageLoader $genericLoader,
        ProductSearchGatewayInterface $searchGateway,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searchGateway = $searchGateway;
    }

    public function load(Request $request, SalesChannelContext $context): SearchPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = SearchPage::createFrom($page);

        if (!$request->query->has('search')) {
            throw new MissingRequestParameterException('search');
        }

        $result = $this->searchGateway->search($request, $context);

        $page->setSearchResult(StorefrontSearchResult::createFrom($result));

        $page->setSearchTerm(
            (string) $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            new SearchPageLoadedEvent($page, $context, $request),
            SearchPageLoadedEvent::NAME
        );

        return $page;
    }
}
