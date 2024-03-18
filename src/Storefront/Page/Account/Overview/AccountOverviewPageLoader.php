<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountOverviewPageLoader
{
    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 - translator will be mandatory from 6.7
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractCustomerRoute $customerRoute,
        private readonly NewsletterAccountPageletLoader $newsletterAccountPageletLoader,
        private readonly ?AbstractTranslator $translator = null
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountOverviewPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOverviewPage::createFrom($page);
        $page->setCustomer($this->loadCustomer($salesChannelContext, $customer));
        $this->setMetaInformation($page);

        $order = $this->loadNewestOrder($salesChannelContext, $request);

        if ($order !== null) {
            $page->setNewestOrder($order);
        }

        $newslAccountPagelet = $this->newsletterAccountPageletLoader->load($request, $salesChannelContext, $customer);

        $page->setNewsletterAccountPagelet($newslAccountPagelet);

        $this->eventDispatcher->dispatch(
            new AccountOverviewPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountOverviewPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($this->translator !== null && $page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        if ($this->translator !== null) {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.overviewMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        }
    }

    private function loadNewestOrder(SalesChannelContext $context, Request $request): ?OrderEntity
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING))
            ->addAssociation('lineItems')
            ->addAssociation('lineItems.cover')
            ->addAssociation('lineItems.downloads.media')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('transactions.stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.stateMachineState')
            ->addAssociation('addresses')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState')
            ->addAssociation('documents.documentType')
            ->setLimit(1)
            ->addAssociation('orderCustomer');

        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt'));

        $apiRequest = $request->duplicate();

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)->getOrders()->getEntities()->first();
    }

    private function loadCustomer(SalesChannelContext $context, CustomerEntity $customer): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultShippingAddress.country');

        return $this->customerRoute->load(new Request(), $context, $criteria, $customer)->getCustomer();
    }
}
