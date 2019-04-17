<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountProfilePageLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        EventDispatcherInterface $eventDispatcher,
        AccountService $accountService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountService = $accountService;
    }

    public function load(Request $request, SalesChannelContext $context): AccountProfilePage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountProfilePage::createFrom($page);

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }
        $page->setCustomer($customer);
        $page->setSalutations($this->accountService->getSalutationList($context));

        $this->eventDispatcher->dispatch(
            AccountProfilePageLoadedEvent::NAME,
            new AccountProfilePageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
