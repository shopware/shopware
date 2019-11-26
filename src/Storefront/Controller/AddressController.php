<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var AddressListingPageLoader
     */
    private $addressListingPageLoader;

    /**
     * @var AddressDetailPageLoader
     */
    private $addressDetailPageLoader;

    public function __construct(
        AddressListingPageLoader $addressListingPageLoader,
        AddressDetailPageLoader $addressDetailPageLoader,
        AddressService $addressService,
        AccountService $accountService
    ) {
        $this->addressService = $addressService;
        $this->accountService = $accountService;
        $this->addressListingPageLoader = $addressListingPageLoader;
        $this->addressDetailPageLoader = $addressDetailPageLoader;
    }

    /**
     * @Route("/account/address", name="frontend.account.address.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function accountAddressOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->addressListingPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address/create", name="frontend.account.address.create.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function accountCreateAddress(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->addressDetailPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/create.html.twig', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function accountEditAddress(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->addressDetailPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/addressbook/edit.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address/default-{type}/{addressId}", name="frontend.account.address.set-default-address", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function switchDefaultAddress(string $type, string $addressId, SalesChannelContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $success = true;

        try {
            if ($type === 'shipping') {
                $this->accountService->setDefaultShippingAddress($addressId, $context);
            } elseif ($type === 'billing') {
                $this->accountService->setDefaultBillingAddress($addressId, $context);
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
     * @Route("/account/address/delete/{addressId}", name="frontend.account.address.delete", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function deleteAddress(string $addressId, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $success = true;

        if (!$addressId) {
            throw new MissingRequestParameterException('addressId');
        }

        try {
            $this->addressService->delete($addressId, $context);
        } catch (InvalidUuidException | AddressNotFoundException | CannotDeleteDefaultAddressException $exception) {
            $success = false;
        }

        return new RedirectResponse($this->generateUrl('frontend.account.address.page', ['addressDeleted' => $success]));
    }

    /**
     * @Route("/account/address/create", name="frontend.account.address.create", options={"seo"="false"}, methods={"POST"})
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.save", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function saveAddress(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        /** @var RequestDataBag $address */
        $address = $data->get('address');

        try {
            $this->addressService->upsert($address, $context);

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
     * @Route("/widgets/account/address-book", name="frontend.account.addressbook", options={"seo"=true}, methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function addressBook(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $viewData = [];
        $viewData = $this->handleChangeableAddresses($viewData, $dataBag);
        $viewData = $this->handleAddressCreation($viewData, $dataBag, $context);
        $viewData = $this->handleAddressSelection($viewData, $dataBag, $context);

        $viewData['page'] = $this->addressListingPageLoader->load($request, $context);

        if ($request->get('redirectTo') || $request->get('forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->renderStorefront('@Storefront/storefront/component/address/address-editor-modal.html.twig', $viewData);
    }

    private function handleAddressCreation(array $viewData, RequestDataBag $dataBag, SalesChannelContext $context): array
    {
        /** @var DataBag|null $addressData */
        $addressData = $dataBag->get('address');
        $addressId = null;

        if ($addressData === null) {
            return $viewData;
        }

        try {
            $addressId = $dataBag->get('id');

            $this->addressService->upsert($addressData, $context);

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

    private function handleChangeableAddresses(array $viewData, RequestDataBag $dataBag): array
    {
        $changeableAddresses = $dataBag->get('changeableAddresses');

        if ($changeableAddresses === null) {
            return $viewData;
        }

        $viewData['changeShipping'] = $changeableAddresses->get('changeShipping');
        $viewData['changeBilling'] = $changeableAddresses->get('changeBilling');

        return $viewData;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    private function handleAddressSelection(array $viewData, RequestDataBag $dataBag, SalesChannelContext $context): array
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
                $address = $this->addressService->getById($addressId, $context);
                $context->getCustomer()->setDefaultShippingAddress($address);
                $this->accountService->setDefaultShippingAddress($addressId, $context);
            } elseif ($addressType === 'billing') {
                $address = $this->addressService->getById($addressId, $context);
                $context->getCustomer()->setDefaultBillingAddress($address);
                $this->accountService->setDefaultBillingAddress($addressId, $context);
            } else {
                $success = false;
            }
        } catch (AddressNotFoundException $exception) {
            $success = false;
        }

        if ($success) {
            $this->addFlash('success', $this->trans('account.addressDefaultChanged'));
        } else {
            $this->addFlash('danger', $this->trans('account.addressDefaultNotChanged'));
        }

        $viewData['success'] = $success;

        return $viewData;
    }
}
