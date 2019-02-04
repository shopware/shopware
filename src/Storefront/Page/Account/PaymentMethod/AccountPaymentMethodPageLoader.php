<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\PaymentMethod\AccountPaymentMethodPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountPaymentMethodPageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AccountPaymentMethodPageletLoader
     */
    private $accountPaymentMethodPageletLoader;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        AccountPaymentMethodPageletLoader $accountPaymentMethodPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountPaymentMethodPageletLoader = $accountPaymentMethodPageletLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountPaymentMethodPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountPaymentMethodPage::createFrom($page);

        $customer = $context->getCustomer();
        if ($customer !== null) {
            $page->setCustomer($customer);
        }

        $page->setPaymentMethods(
            $this->accountPaymentMethodPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountPaymentMethodPageLoadedEvent::NAME,
            new AccountPaymentMethodPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
