<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\Order\AccountOrderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageLoader implements PageLoaderInterface
{
    /**
     * @var AccountOrderPageletLoader|PageLoaderInterface
     */
    private $accountOrderPageletLoader;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageLoaderInterface $accountOrderPageletLoader,
        PageLoaderInterface $pageWithHeaderLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountOrderPageletLoader = $accountOrderPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
    }

    public function load(Request $request, SalesChannelContext $context): AccountOrderPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountOrderPage::createFrom($page);

        $page->setOrders(
            $this->accountOrderPageletLoader->load($request, $context)
        );

        $page->setCustomer($context->getCustomer());

        $this->eventDispatcher->dispatch(
            AccountOrderPageLoadedEvent::NAME,
            new AccountOrderPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
