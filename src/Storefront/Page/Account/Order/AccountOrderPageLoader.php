<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountOrderPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AccountService $accountService
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOrderPage
    {
        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOrderPage::createFrom($page);

        $page->getMetaInformation()?->setRobots('noindex,follow');

        $page->setOrders(StorefrontSearchResult::createFrom($this->getOrders($request, $salesChannelContext)));

        $page->setDeepLinkCode($request->get('deepLinkCode'));

        $firstOrder = $page->getOrders()->getEntities()->first();
        $orderCustomerId = $firstOrder?->getOrderCustomer()?->getCustomer()?->getId();
        if ($request->get('deepLinkCode') && $orderCustomerId !== null) {
            $this->accountService->loginById(
                $orderCustomerId,
                $salesChannelContext
            );
        }

        $this->eventDispatcher->dispatch(
            new AccountOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws GuestNotAuthenticatedException
     * @throws WrongGuestCredentialsException
     *
     * @return EntitySearchResult<OrderCollection>
     */
    private function getOrders(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = $this->createCriteria($request);
        $apiRequest = new Request();

        // Add email and zipcode for guest customer verification in order view
        if ($request->get('email', false) && $request->get('zipcode', false)) {
            $apiRequest->query->set('email', $request->get('email'));
            $apiRequest->query->set('zipcode', $request->get('zipcode'));
        }

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        $responseStruct = $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria);

        return $responseStruct->getOrders();
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = $request->get('limit');
        $limit = $limit ? (int) $limit : 10;
        $page = $request->get('p');
        $page = $page ? (int) $page : 1;

        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING))
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('lineItems')
            ->addAssociation('lineItems.cover')
            ->addAssociation('lineItems.downloads.media')
            ->addAssociation('addresses')
            ->addAssociation('currency')
            ->addAssociation('documents.documentType')
            ->setLimit($limit)
            ->setOffset(($page - 1) * $limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $criteria
            ->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt'));

        $criteria
            ->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));

        if ($request->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')));
        }

        return $criteria;
    }
}
