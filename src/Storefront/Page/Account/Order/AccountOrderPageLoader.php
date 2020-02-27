<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractAccountOrderRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractAccountOrderRoute
     */
    private $orderRoute;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AbstractAccountOrderRoute $orderRoute,
        RequestCriteriaBuilder $requestCriteriaBuilder
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRoute = $orderRoute;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOrderPage
    {
        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOrderPage::createFrom($page);

        $page->setOrders(StorefrontSearchResult::createFrom($this->getOrders($request, $salesChannelContext)));

        $this->eventDispatcher->dispatch(
            new AccountOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getOrders(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = $this->createCriteria($request);
        $routeRequest = new Request();
        $routeRequest->query->replace($this->requestCriteriaBuilder->toArray($criteria));

        return $this->orderRoute->load($routeRequest, $context)->getOrders();
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = (int) $request->query->get('limit', 10);
        $page = (int) $request->query->get('p', 1);

        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING))
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems')
            ->setLimit($limit)
            ->setOffset(($page - 1) * $limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        if ($request->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')));
        }

        return $criteria;
    }
}
