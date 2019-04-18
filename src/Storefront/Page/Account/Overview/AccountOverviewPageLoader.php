<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOverviewPageLoader implements PageLoaderInterface
{
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
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
    }

    public function load(Request $request, SalesChannelContext $context): AccountOverviewPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountOverviewPage::createFrom($page);

        $customer = $context->getCustomer();

        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }
        $page->setCustomer($customer);

        $this->eventDispatcher->dispatch(
            AccountOverviewPageLoadedEvent::NAME,
            new AccountOverviewPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
