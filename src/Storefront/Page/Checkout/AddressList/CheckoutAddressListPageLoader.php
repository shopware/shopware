<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\AddressList;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Account\AddressList\AccountAddressListPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutAddressListPageLoader implements PageLoaderInterface
{
    /**
     * @var AccountAddressListPageletLoader|PageLoaderInterface
     */
    private $accountAddressPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageLoaderInterface $accountAddressPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountAddressPageletLoader = $accountAddressPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): CheckoutAddressListPage
    {
        $page = new CheckoutAddressListPage($context);

        $page->setAddresses($this->accountAddressPageletLoader->load($request, $context));

        $this->eventDispatcher->dispatch(
            CheckoutAddressListPageLoadedEvent::NAME,
            new CheckoutAddressListPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
