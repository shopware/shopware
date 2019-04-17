<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\PaymentMethod\AccountPaymentMethodPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AccountPaymentMethodPageletLoader|PageLoaderInterface
     */
    private $accountPaymentMethodPageletLoader;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        PageLoaderInterface $accountPaymentMethodPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountPaymentMethodPageletLoader = $accountPaymentMethodPageletLoader;
    }

    public function load(Request $request, SalesChannelContext $context): AccountPaymentMethodPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountPaymentMethodPage::createFrom($page);

        $customer = $context->getCustomer();

        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }
        $page->setCustomer($customer);

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
