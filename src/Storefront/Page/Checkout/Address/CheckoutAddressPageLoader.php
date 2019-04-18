<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Address;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutAddressPageLoader implements PageLoaderInterface
{
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
        EventDispatcherInterface $eventDispatcher,
        AddressService $addressService,
        AccountService $accountService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->addressService = $addressService;
        $this->accountService = $accountService;
    }

    public function load(Request $request, SalesChannelContext $context): CheckoutAddressPage
    {
        $page = new CheckoutAddressPage($context);

        $page = CheckoutAddressPage::createFrom($page);

        $page->setCountries($this->addressService->getCountryList($context));

        $page->setSalutations($this->accountService->getSalutationList($context));

        $address = $this->getAddress($request, $context);
        if ($address) {
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            CheckoutAddressPageLoadedEvent::NAME,
            new CheckoutAddressPageLoadedEvent($page, $context, $request)
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
