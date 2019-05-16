<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountLoginPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        GenericPageLoader $genericLoader,
        AddressService $addressService,
        AccountService $accountService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->addressService = $addressService;
        $this->accountService = $accountService;
    }

    public function load(Request $request, SalesChannelContext $context): AccountLoginPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AccountLoginPage::createFrom($page);

        $page->setCountries($this->addressService->getCountryList($context));
        $page->setSalutations($this->accountService->getSalutationList($context));

        $this->eventDispatcher->dispatch(
            AccountLoginPageLoadedEvent::NAME,
            new AccountLoginPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
