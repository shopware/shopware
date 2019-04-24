<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Address;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressPageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        AddressService $addressService,
        AccountService $accountService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->addressService = $addressService;
        $this->accountService = $accountService;
    }

    public function load(Request $request, SalesChannelContext $context): AccountAddressPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountAddressPage::createFrom($page);

        $page->setCountries(
            $this->addressService->getCountryList($context)
        );

        $page->setCustomer($context->getCustomer());

        $page->setSalutations($this->accountService->getSalutationList($context));

        $address = $this->getAddress($request, $context);
        if ($address) {
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            AccountAddressPageLoadedEvent::NAME,
            new AccountAddressPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function getAddress(Request $request, SalesChannelContext $context): ?CustomerAddressEntity
    {
        if ($request->request->has('address')) {
            return (new CustomerAddressEntity())->assign($request->request->get('address', []));
        }

        $addressId = $request->attributes->get('addressId');
        if ($addressId) {
            return $this->addressService->getById((string) $addressId, $context);
        }

        return null;
    }
}
