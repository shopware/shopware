<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AddressBook;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\HttpFoundation\Request;

class AddressBookPageletLoader implements PageLoaderInterface
{
    /**
     * @var PageLoaderInterface
     */
    private $addressListPageletLoader;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        PageLoaderInterface $addressListPageletLoader,
        AddressService $addressService,
        AccountService $accountService
    ) {
        $this->addressListPageletLoader = $addressListPageletLoader;
        $this->addressService = $addressService;
        $this->accountService = $accountService;
    }

    public function load(Request $request, SalesChannelContext $context): AddressBookPagelet
    {
        /** @var CountryCollection $countries */
        $countries = $this->addressService->getCountryList($context);

        /** @var SalutationCollection $salutations */
        $salutations = $this->accountService->getSalutationList($context);

        $result = new AddressBookPagelet($salutations, $countries);

        if (!empty($addressId = $request->get('id', null))) {
            if ($request->isMethod('get')) {
                /** @var StorefrontSearchResult $addresses */
                $addresses = $this->addressListPageletLoader->load($request, $context);
                $result->setAddresses($addresses);
            }

            $address = $this->addressService->getById($addressId, $context);
            $result->setAddress($address);
        }

        return $result;
    }
}
