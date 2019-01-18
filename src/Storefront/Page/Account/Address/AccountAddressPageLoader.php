<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Address;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Storefront\AccountService;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountAddressPageLoader
{
    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        AccountService $accountService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountService = $accountService;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountAddressPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountAddressPage::createFrom($page);

        $page->setCountries(
            $this->accountService->getCountryList($context)
        );

        $addressId = $request->optionalGet('addressId');

        if ($addressId && Uuid::isValid((string) $addressId)) {
            $address = $this->accountService->getAddressById((string) $addressId, $context);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            AccountAddressPageLoadedEvent::NAME,
            new AccountAddressPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
