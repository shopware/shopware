<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteInterface;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageLoader
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
     * @var PaymentMethodRouteInterface
     */
    private $paymentMethodRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        PaymentMethodRouteInterface $paymentMethodRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentMethodRoute = $paymentMethodRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountPaymentMethodPage
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountPaymentMethodPage::createFrom($page);

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountPaymentMethodPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getPaymentMethods(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', 1);

        return $this->paymentMethodRoute->load($request, $salesChannelContext)->getPaymentMethods();
    }
}
