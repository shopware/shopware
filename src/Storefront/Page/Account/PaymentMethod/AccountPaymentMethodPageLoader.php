<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - this page is removed as customer default payment method will be removed
 *
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class AccountPaymentMethodPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractTranslator $translator,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountPaymentMethodPage
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method will be removed and the last used payment method is prioritized.');

        if (!$salesChannelContext->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountPaymentMethodPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setPaymentMethods(
            $this->loadPaymentMethods($request, $salesChannelContext)
                ->filterByActiveRules($salesChannelContext)
        );

        $this->eventDispatcher->dispatch(
            new AccountPaymentMethodPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function loadPaymentMethods(Request $request, SalesChannelContext $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('generic-page::payment-methods');

        $event = new PaymentMethodRouteRequestEvent($request, $request->duplicate(), $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->paymentMethodRoute
            ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
            ->getPaymentMethods();
    }

    protected function setMetaInformation(AccountPaymentMethodPage $page): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method will be removed and the last used payment method is prioritized.');

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.paymentMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }
}
