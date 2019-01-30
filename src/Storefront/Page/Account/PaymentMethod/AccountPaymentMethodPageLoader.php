<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\PaymentMethod\AccountPaymentMethodPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountPaymentMethodPageLoader
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

        $page->setCustomer($context->getCustomer());

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
