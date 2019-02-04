<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountOverviewPageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountOverviewPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountOverviewPage::createFrom($page);

        $customer = $context->getCustomer();
        if ($customer !== null) {
            $page->setCustomer($customer);
        }

        $this->eventDispatcher->dispatch(
            AccountOverviewPageLoadedEvent::NAME,
            new AccountOverviewPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
