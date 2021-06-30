<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AddressController extends StorefrontController
{
    private AccountService $accountService;

    private AddressListingPageLoader $addressListingPageLoader;

    private AddressDetailPageLoader $addressDetailPageLoader;

    private AbstractListAddressRoute $listAddressRoute;

    private AbstractUpsertAddressRoute $updateAddressRoute;

    private AbstractDeleteAddressRoute $deleteAddressRoute;

    public function __construct(
        AddressListingPageLoader $addressListingPageLoader,
        AddressDetailPageLoader $addressDetailPageLoader,
        AccountService $accountService,
        AbstractListAddressRoute $listAddressRoute,
        AbstractUpsertAddressRoute $updateAddressRoute,
        AbstractDeleteAddressRoute $deleteAddressRoute
    ) {
        $this->accountService = $accountService;
        $this->addressListingPageLoader = $addressListingPageLoader;
        $this->addressDetailPageLoader = $addressDetailPageLoader;
        $this->listAddressRoute = $listAddressRoute;
        $this->updateAddressRoute = $updateAddressRoute;
        $this->deleteAddressRoute = $deleteAddressRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/address", name="frontend.account.address.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function accountAddressOverview(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->addressListingPageLoader->load($request, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/address/create", name="frontend.account.address.create.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function accountCreateAddress(Request $request, RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/create.html.twig', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function accountEditAddress(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/edit.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/address/default-{type}/{addressId}", name="frontend.account.address.set-default-address", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function switchDefaultAddress(string $type, string $addressId, SalesChannelContext $context, CustomerEntity $customer): RedirectResponse
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $success = true;

        try {
            if ($type === 'shipping') {
                $this->accountService->setDefaultShippingAddress($addressId, $context, $customer);
            } elseif ($type === 'billing') {
                $this->accountService->setDefaultBillingAddress($addressId, $context, $customer);
            } else {
                $success = false;
            }
        } catch (AddressNotFoundException $exception) {
            $success = false;
        }

        return new RedirectResponse(
            $this->generateUrl('frontend.account.address.page', ['changedDefaultAddress' => $success])
        );
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/address/delete/{addressId}", name="frontend.account.address.delete", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function deleteAddress(string $addressId, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $success = true;

        if (!$addressId) {
            throw new MissingRequestParameterException('addressId');
        }

        try {
            $this->deleteAddressRoute->delete($addressId, $context, $customer);
        } catch (InvalidUuidException | AddressNotFoundException | CannotDeleteDefaultAddressException $exception) {
            $success = false;
        }

        return new RedirectResponse($this->generateUrl('frontend.account.address.page', ['addressDeleted' => $success]));
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/address/create", name="frontend.account.address.create", options={"seo"="false"}, methods={"POST"})
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.save", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
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

    /**
     * @Since("6.0.0.0")
     * @LoginRequired(allowGuest=true)
     * @Route("/widgets/account/address-book", name="frontend.account.addressbook", options={"seo"=true}, methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function addressBook(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $viewData = [];
        $viewData = $this->handleChangeableAddresses($viewData, $dataBag, $context, $customer);
        $viewData = $this->handleAddressCreation($viewData, $dataBag, $context, $customer);
        $viewData = $this->handleAddressSelection($viewData, $dataBag, $context, $customer);

        $viewData['page'] = $this->addressListingPageLoader->load($request, $context, $customer);

        if ($request->get('redirectTo') || $request->get('forwardTo')) {
            return $this->createActionResponse($request);
        }

        $response = $this->renderStorefront('@Storefront/storefront/component/address/address-editor-modal.html.twig', $viewData);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    private function handleAddressCreation(array $viewData, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): array
    {
        /** @var DataBag|null $addressData */
        $addressData = $dataBag->get('address');
        $addressId = null;

        if ($addressData === null) {
            return $viewData;
        }

        try {
            $addressId = $dataBag->get('id');

            $this->updateAddressRoute->upsert(
                $addressData->get('id'),
                $addressData->toRequestDataBag(),
                $context,
                $customer
            );

            $success = true;
            $messages = ['type' => 'success', 'text' => $this->trans('account.addressSaved')];
        } catch (\Exception $exception) {
            $success = false;
            $messages = ['type' => 'danger', 'text' => $this->trans('error.message-default')];
        }

        $viewData['addressId'] = $addressId;
        $viewData['success'] = $success;
        $viewData['messages'] = $messages;

        return $viewData;
    }

    private function handleChangeableAddresses(array $viewData, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): array
    {
        $changeableAddresses = $dataBag->get('changeableAddresses');

        if ($changeableAddresses === null) {
            return $viewData;
        }

        $viewData['changeShipping'] = $changeableAddresses->get('changeShipping');
        $viewData['changeBilling'] = $changeableAddresses->get('changeBilling');

        $addressId = $dataBag->get('id');

        if (!$addressId) {
            return $viewData;
        }

        $viewData['address'] = $this->getById($addressId, $context, $customer);

        return $viewData;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    private function handleAddressSelection(array $viewData, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): array
    {
        $selectedAddress = $dataBag->get('selectAddress');

        if ($selectedAddress === null) {
            return $viewData;
        }

        $addressType = $selectedAddress->get('type');
        $addressId = $selectedAddress->get('id');

        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $success = true;

        try {
            if ($addressType === 'shipping') {
                $address = $this->getById($addressId, $context, $customer);
                $context->getCustomer()->setDefaultShippingAddress($address);
                $this->accountService->setDefaultShippingAddress($addressId, $context, $customer);
            } elseif ($addressType === 'billing') {
                $address = $this->getById($addressId, $context, $customer);
                $context->getCustomer()->setDefaultBillingAddress($address);
                $this->accountService->setDefaultBillingAddress($addressId, $context, $customer);
            } else {
                $success = false;
            }
        } catch (AddressNotFoundException $exception) {
            $success = false;
        }

        if ($success) {
            $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));
        } else {
            $this->addFlash(self::DANGER, $this->trans('account.addressDefaultNotChanged'));
        }

        $viewData['success'] = $success;

        return $viewData;
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
}
