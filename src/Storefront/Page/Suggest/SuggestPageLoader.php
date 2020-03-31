<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SuggestPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractProductSuggestRoute
     */
    private $productSuggestRoute;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        AbstractProductSuggestRoute $productSuggestRoute,
        GenericPageLoaderInterface $genericLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->productSuggestRoute = $productSuggestRoute;
        $this->genericLoader = $genericLoader;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SuggestPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = SuggestPage::createFrom($page);

        $page->setSearchResult($this->productSuggestRoute->load($request, $salesChannelContext)->getListingResult());

        $page->setSearchTerm($request->query->get('search'));

        $this->eventDispatcher->dispatch(
            new SuggestPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
