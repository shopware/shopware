<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\AddressList;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\AddressList\AccountAddressListPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressListPageLoader implements PageLoaderInterface
{
    /**
     * @var AccountAddressListPageletLoader|PageLoaderInterface
     */
    private $accountAddressPageletLoader;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        PageLoaderInterface $accountAddressPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountAddressPageletLoader = $accountAddressPageletLoader;
    }

    public function load(Request $request, SalesChannelContext $context): AccountAddressListPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountAddressListPage::createFrom($page);

        $page->setAddresses(
            $this->accountAddressPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountAddressListPageLoadedEvent::NAME,
            new AccountAddressListPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
