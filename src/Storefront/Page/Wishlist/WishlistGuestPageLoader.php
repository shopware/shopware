<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadGuestWishlistRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class WishlistGuestPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractLoadGuestWishlistRoute
     */
    private $guestWishlistLoadRoute;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericPageLoader;

    public function __construct(
        GenericPageLoaderInterface $genericPageLoader,
        AbstractLoadGuestWishlistRoute $guestWishlistLoadRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->guestWishlistLoadRoute = $guestWishlistLoadRoute;
        $this->eventDispatcher = $eventDispatcher;
        $this->genericPageLoader = $genericPageLoader;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $context): WishlistGuestPage
    {
        $page = $this->genericPageLoader->load($request, $context);
        $page = WishlistGuestPage::createFrom($page);

        $this->eventDispatcher->dispatch(new WishlistGuestPageLoadedEvent($page, $context, $request));

        return $page;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function loadPagelet(Request $request, SalesChannelContext $context): WishlistGuestPagelet
    {
        $criteria = $this->createCriteria();

        $page = new WishlistGuestPagelet();

        $page->setSearchResult($this->guestWishlistLoadRoute->load($request, $context, $criteria));

        $this->eventDispatcher->dispatch(new WishlistGuestPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    private function createCriteria(): Criteria
    {
        return (new Criteria())
            ->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }
}
