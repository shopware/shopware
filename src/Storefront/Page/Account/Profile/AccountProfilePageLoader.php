<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountProfilePageLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountProfilePage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountProfilePage::createFrom($page);

        $customer = $context->getCustomer();
        if ($customer !== null) {
            $page->setCustomer($customer);
        }

        $this->eventDispatcher->dispatch(
            AccountProfilePageLoadedEvent::NAME,
            new AccountProfilePageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
