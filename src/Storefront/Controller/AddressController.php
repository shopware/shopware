<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Order\Transformer\CustomerTransformer;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Address\AddressEditorModalStruct;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedHook;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Shopware\Storefront\Page\Address\Listing\AddressBookWidgetLoadedHook;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoadedHook;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class AddressController extends StorefrontController
{
    private const ADDRESS_TYPE_BILLING = 'billing';
    private const ADDRESS_TYPE_SHIPPING = 'shipping';

    /**
     * @internal
     */
    public function __construct(
        private readonly AddressListingPageLoader $addressListingPageLoader,
        private readonly AddressDetailPageLoader $addressDetailPageLoader,
        private readonly AccountService $accountService,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly AbstractUpsertAddressRoute $updateAddressRoute,
        private readonly AbstractDeleteAddressRoute $deleteAddressRoute,
        private readonly AbstractChangeCustomerProfileRoute $updateCustomerProfileRoute
    ) {
    }

    #[Route(path: '/account/address', name: 'frontend.account.address.page', options: ['seo' => false], defaults: ['_loginRequired' => true, '_noStore' => true], methods: ['GET'])]
    public function accountAddressOverview(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->addressListingPageLoader->load($request, $context, $customer);

        $this->hook(new AddressListingPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/index.html.twig', ['page' => $page]);
    }

    #[Route(path: '/account/address/create', name: 'frontend.account.address.create.page', options: ['seo' => false], defaults: ['_loginRequired' => true, '_noStore' => true], methods: ['GET'])]
    public function accountCreateAddress(Request $request, RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $customer);

        $this->hook(new AddressDetailPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/create.html.twig', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(path: '/account/address/{addressId}', name: 'frontend.account.address.edit.page', options: ['seo' => false], defaults: ['_loginRequired' => true, '_noStore' => true], methods: ['GET'])]
    public function accountEditAddress(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $customer);

        $this->hook(new AddressDetailPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/edit.html.twig', ['page' => $page]);
    }

    #[Route(path: '/account/address/default-{type}/{addressId}', name: 'frontend.account.address.set-default-address', defaults: ['_loginRequired' => true], methods: ['POST'])]
    public function switchDefaultAddress(string $type, string $addressId, SalesChannelContext $context, CustomerEntity $customer): RedirectResponse
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $success = true;

        try {
            if ($type === self::ADDRESS_TYPE_SHIPPING) {
                $this->accountService->setDefaultShippingAddress($addressId, $context, $customer);
            } elseif ($type === self::ADDRESS_TYPE_BILLING) {
                $this->accountService->setDefaultBillingAddress($addressId, $context, $customer);
            } else {
                $success = false;
            }
        } catch (AddressNotFoundException) {
            $success = false;
        }

        return new RedirectResponse(
            $this->generateUrl('frontend.account.address.page', ['changedDefaultAddress' => $success])
        );
    }

    #[Route(path: '/account/address/delete/{addressId}', name: 'frontend.account.address.delete', options: ['seo' => false], defaults: ['_loginRequired' => true], methods: ['POST'])]
    public function deleteAddress(string $addressId, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $success = true;

        if (!$addressId) {
            throw RoutingException::missingRequestParameter('addressId');
        }

        try {
            $this->deleteAddressRoute->delete($addressId, $context, $customer);
        } catch (InvalidUuidException | AddressNotFoundException | CannotDeleteDefaultAddressException) {
            $success = false;
        }

        return new RedirectResponse($this->generateUrl('frontend.account.address.page', ['addressDeleted' => $success]));
    }

    #[Route(path: '/account/address/create', name: 'frontend.account.address.create', options: ['seo' => false], defaults: ['_loginRequired' => true], methods: ['POST'])]
    #[Route(path: '/account/address/{addressId}', name: 'frontend.account.address.edit.save', options: ['seo' => false], defaults: ['_loginRequired' => true], methods: ['POST'])]
    public function saveAddress(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        /** @var RequestDataBag $address */
        $address = $data->get('address');

        try {
            $this->updateAddressRoute->upsert(
                $address->get('id'),
                $address->toRequestDataBag(),
                $context,
                $customer
            );

            return new RedirectResponse($this->generateUrl('frontend.account.address.page', ['addressSaved' => true]));
        } catch (ConstraintViolationException $formViolations) {
        }

        if (!$address->get('id')) {
            return $this->forwardToRoute('frontend.account.address.create.page', ['formViolations' => $formViolations]);
        }

        return $this->forwardToRoute(
            'frontend.account.address.edit.page',
            ['formViolations' => $formViolations],
            ['addressId' => $address->get('id')]
        );
    }

    #[Route(path: '/widgets/account/address-book', name: 'frontend.account.addressbook', options: ['seo' => true], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true, '_loginRequiredAllowGuest' => true], methods: ['POST'])]
    public function addressBook(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $viewData = new AddressEditorModalStruct();
        $params = [];

        try {
            $this->handleChangeableAddresses($viewData, $dataBag, $context, $customer);
            $this->handleAddressCreation($viewData, $dataBag, $context, $customer);
            $this->handleAddressSelection($viewData, $dataBag, $context, $customer);

            $page = $this->addressListingPageLoader->load($request, $context, $customer);

            $this->hook(new AddressBookWidgetLoadedHook($page, $context));

            $viewData->setPage($page);
            $this->handleCustomerVatIds($dataBag, $context, $customer);
        } catch (ConstraintViolationException $formViolations) {
            $params['formViolations'] = $formViolations;
            $params['postedData'] = $dataBag->get('address');
        } catch (\Exception) {
            $viewData->setSuccess(false);
            $viewData->setMessages([
                'type' => self::DANGER,
                'text' => $this->trans('error.message-default'),
            ]);
        }

        if ($request->get('redirectTo') || $request->get('forwardTo')) {
            return $this->createActionResponse($request);
        }
        $params = array_merge($params, $viewData->getVars());

        $response = $this->renderStorefront(
            '@Storefront/storefront/component/address/address-editor-modal.html.twig',
            $params
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    private function handleAddressCreation(
        AddressEditorModalStruct $viewData,
        RequestDataBag $dataBag,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): void {
        /** @var DataBag|null $addressData */
        $addressData = $dataBag->get('address');

        if ($addressData === null) {
            return;
        }

        $response = $this->updateAddressRoute->upsert(
            $addressData->get('id'),
            $addressData->toRequestDataBag(),
            $context,
            $customer
        );

        $addressId = $response->getAddress()->getId();

        $addressType = null;

        if ($viewData->isChangeBilling()) {
            $addressType = self::ADDRESS_TYPE_BILLING;
        } elseif ($viewData->isChangeShipping()) {
            $addressType = self::ADDRESS_TYPE_SHIPPING;
        }

        // prepare data to set newly created address as customers default
        if ($addressType) {
            $dataBag->set('selectAddress', new RequestDataBag([
                'id' => $addressId,
                'type' => $addressType,
            ]));
        }

        $viewData->setAddressId($addressId);
        $viewData->setSuccess(true);
        $viewData->setMessages(['type' => 'success', 'text' => $this->trans('account.addressSaved')]);
    }

    private function handleChangeableAddresses(
        AddressEditorModalStruct $viewData,
        RequestDataBag $dataBag,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): void {
        $changeableAddresses = $dataBag->get('changeableAddresses');

        if ($changeableAddresses === null) {
            return;
        }

        $viewData->setChangeShipping((bool) $changeableAddresses->get('changeShipping'));
        $viewData->setChangeBilling((bool) $changeableAddresses->get('changeBilling'));

        $addressId = $dataBag->get('id');

        if (!$addressId) {
            return;
        }

        $viewData->setAddress($this->getById($addressId, $context, $customer));
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    private function handleAddressSelection(
        AddressEditorModalStruct $viewData,
        RequestDataBag $dataBag,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): void {
        $selectedAddress = $dataBag->get('selectAddress');

        if ($selectedAddress === null) {
            return;
        }

        $addressType = $selectedAddress->get('type');
        $addressId = $selectedAddress->get('id');

        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $success = true;

        try {
            if ($addressType === self::ADDRESS_TYPE_SHIPPING) {
                $address = $this->getById($addressId, $context, $customer);
                $customer->setDefaultShippingAddress($address);
                $this->accountService->setDefaultShippingAddress($addressId, $context, $customer);
            } elseif ($addressType === self::ADDRESS_TYPE_BILLING) {
                $address = $this->getById($addressId, $context, $customer);
                $customer->setDefaultBillingAddress($address);
                $this->accountService->setDefaultBillingAddress($addressId, $context, $customer);
            } else {
                $success = false;
            }
        } catch (AddressNotFoundException) {
            $success = false;
        }

        if ($success) {
            $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));
        } else {
            $this->addFlash(self::DANGER, $this->trans('account.addressDefaultNotChanged'));
        }

        $viewData->setSuccess($success);
    }

    private function getById(string $addressId, SalesChannelContext $context, CustomerEntity $customer): CustomerAddressEntity
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $customer)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw new AddressNotFoundException($addressId);
        }

        return $address;
    }

    private function handleCustomerVatIds(RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): void
    {
        if (!$dataBag->has('vatIds')) {
            return;
        }

        $newVatIds = $dataBag->get('vatIds')->all();
        $oldVatIds = $customer->getVatIds() ?? [];
        if (!array_diff($newVatIds, $oldVatIds) && !array_diff($oldVatIds, $newVatIds)) {
            return;
        }

        $dataCustomer = CustomerTransformer::transform($customer);
        $dataCustomer['vatIds'] = $newVatIds;
        $dataCustomer['accountType'] = $customer->getCompany() === null ? CustomerEntity::ACCOUNT_TYPE_PRIVATE : CustomerEntity::ACCOUNT_TYPE_BUSINESS;

        $newDataBag = new RequestDataBag($dataCustomer);

        $this->updateCustomerProfileRoute->change($newDataBag, $context, $customer);
    }
}
