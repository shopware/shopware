<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\Order\AccountOrderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountOrderPageLoader
{
    /**
     * @var AccountOrderPageletLoader
     */
    private $accountOrderPageletLoader;

    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountOrderPageletLoader $accountOrderPageletLoader,
        PageWithHeaderLoader $pageWithHeaderLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountOrderPageletLoader = $accountOrderPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountOrderPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountOrderPage::createFrom($page);

        $page->setOrders(
            $this->accountOrderPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountOrderPageLoadedEvent::NAME,
            new AccountOrderPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
