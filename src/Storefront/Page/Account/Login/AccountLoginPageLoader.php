<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Storefront\AccountService;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountLoginPageLoader implements PageLoaderInterface
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
        AccountService $accountService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountService = $accountService;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountLoginPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountLoginPage::createFrom($page);

        $page->setCountries($this->accountService->getCountryList($context));
        $page->setSalutations($this->accountService->getSalutationList($context));

        $this->eventDispatcher->dispatch(
            AccountLoginPageLoadedEvent::NAME,
            new AccountLoginPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
