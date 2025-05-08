<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
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
use Shopware\Core\Framework\Uuid\UuidException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
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
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('framework')]
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
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService
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
            throw UuidException::invalidUuid($addressId);
        }

        try {
            if ($type === self::ADDRESS_TYPE_SHIPPING) {
                $this->accountService->setDefaultShippingAddress($addressId, $context, $customer);
                $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));
            } elseif ($type === self::ADDRESS_TYPE_BILLING) {
                $this->accountService->setDefaultBillingAddress($addressId, $context, $customer);
                $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));
            } else {
                $this->addFlash(self::DANGER, $this->trans('account.addressDefaultNotChanged'));
            }
        } catch (AddressNotFoundException) {
            $this->addFlash(self::DANGER, $this->trans('account.addressDefaultNotChanged'));
        }

        return new RedirectResponse($this->generateUrl('frontend.account.address.page'));
    }

    #[Route(path: '/account/address/switch', name: 'frontend.account.address.switch-default', defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function checkoutSwitchDefaultAddress(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): RedirectResponse
    {
        match ($data->get('type')) {
            self::ADDRESS_TYPE_SHIPPING => $this->accountService->setDefaultShippingAddress($data->get('id'), $context, $customer),
            self::ADDRESS_TYPE_BILLING => $this->accountService->setDefaultBillingAddress($data->get('id'), $context, $customer),
            default => throw RoutingException::invalidRequestParameter('type'),
        };

        $contextToken = $this->contextSwitchRoute->switchContext($data, $context);

        $this->salesChannelContextService->get(new SalesChannelContextServiceParameters(
            $context->getSalesChannelId(),
            $contextToken->getToken()
        ));

        $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));

        return new RedirectResponse(
            $this->generateUrl('frontend.account.addressmanager.get')
        );
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

            $this->addFlash(self::SUCCESS, $this->trans('account.addressSaved'));

            return new RedirectResponse($this->generateUrl('frontend.account.address.page'));
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

    #[Route(path: '/account/address/delete/{addressId}', name: 'frontend.account.address.delete', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function deleteAddress(string $addressId, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        if (!$addressId) {
            throw RoutingException::missingRequestParameter('addressId');
        }

        try {
            $this->deleteAddressRoute->delete($addressId, $context, $customer);
            $this->addFlash(self::SUCCESS, $this->trans('account.addressDeleted'));
        } catch (InvalidUuidException|AddressNotFoundException|CannotDeleteDefaultAddressException|CustomerException) {
            $this->addFlash(self::DANGER, $this->trans('account.addressNotDeleted'));
        }

        return new RedirectResponse($this->generateUrl('frontend.account.address.page'));
    }

    #[Route(path: '/widgets/account/address-manager/switch', name: 'frontend.account.addressmanager.switch', options: ['seo' => true], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true, '_loginRequiredAllowGuest' => true], methods: ['POST'])]
    public function addressManagerSwitch(RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        if (!$dataBag->get(SalesChannelContextService::SHIPPING_ADDRESS_ID)) {
            $dataBag->remove(SalesChannelContextService::SHIPPING_ADDRESS_ID);
        }

        if (!$dataBag->get(SalesChannelContextService::BILLING_ADDRESS_ID)) {
            $dataBag->remove(SalesChannelContextService::BILLING_ADDRESS_ID);
        }

        $this->contextSwitchRoute->switchContext($dataBag, $context);

        $this->addFlash(self::SUCCESS, $this->trans('account.addressSuccessfulChange'));

        return new RedirectResponse(
            $this->generateUrl('frontend.checkout.confirm.page')
        );
    }

    #[Route(path: '/widgets/account/address-manager', name: 'frontend.account.addressmanager.get', options: ['seo' => true], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true, '_loginRequiredAllowGuest' => true], methods: ['GET'])]
    public function addressManager(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $viewData = new AddressEditorModalStruct();

        $page = $this->addressListingPageLoader->load($request, $context, $customer);
        $this->hook(new AddressBookWidgetLoadedHook($page, $context));
        $viewData->setPage($page);

        $response = $this->renderStorefront(
            '@Storefront/storefront/component/address/address-manager-modal.html.twig',
            $viewData->getVars()
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(path: '/widgets/account/address-manager/{addressId?}', name: 'frontend.account.addressmanager', options: ['seo' => true], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true, '_loginRequiredAllowGuest' => true], methods: ['POST'])]
    public function addressManagerUpsert(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer, ?string $addressId = null, #[MapQueryParameter] ?string $type = null): Response
    {
        $viewData = new AddressEditorModalStruct();

        match ($type) {
            self::ADDRESS_TYPE_SHIPPING => $viewData->setChangeShipping(true),
            self::ADDRESS_TYPE_BILLING => $viewData->setChangeBilling(true),
            default => throw RoutingException::invalidRequestParameter('type'),
        };

        $params = [];

        if ($addressId) {
            $params['postedAddress'] = $this->getById($addressId, $context, $customer);
        }

        /** @var RequestDataBag|null $addressData */
        $addressData = $dataBag->get('address');

        try {
            // if there is no data in the dataBag, the create form will be rendered
            if ($addressData !== null && $addressData->count() !== 0) {
                $addressData->set('id', $addressId);
                $this->handleAddressCreation($viewData, $addressData, $context, $customer);
                $this->addFlash(self::SUCCESS, $this->trans('account.addressSaved'));

                return new NoContentResponse();
            }
        } catch (ConstraintViolationException $formViolations) {
            $params['formViolations'] = $formViolations;
            $params['postedAddress'] = $addressData;
        } catch (\Throwable) {
            $viewData->setSuccess(false);
            $viewData->setMessages([
                'type' => self::DANGER,
                'text' => $this->trans('error.message-default'),
            ]);
        }

        $page = $this->addressListingPageLoader->load($request, $context, $customer);
        $this->hook(new AddressBookWidgetLoadedHook($page, $context));
        $viewData->setPage($page);

        $params = array_merge($params, $viewData->getVars());

        $response = $this->renderStorefront(
            '@Storefront/storefront/component/address/address-manager-modal-create-address.html.twig',
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
        $response = $this->updateAddressRoute->upsert(
            $dataBag->get('id'),
            $dataBag->toRequestDataBag(),
            $context,
            $customer
        );

        $addressId = $response->getAddress()->getId();

        $viewData->setAddressId($addressId);
        $viewData->setSuccess(true);
        $viewData->setMessages(['type' => 'success', 'text' => $this->trans('account.addressSaved')]);

        if (!$viewData->isChangeShipping() && !$viewData->isChangeBilling()) {
            return;
        }

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(
            $viewData->isChangeShipping()
                ? SalesChannelContextService::SHIPPING_ADDRESS_ID
                : SalesChannelContextService::BILLING_ADDRESS_ID,
            $addressId
        );

        $this->contextSwitchRoute->switchContext($requestDataBag, $context);
    }

    private function getById(
        string $addressId,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): CustomerAddressEntity {
        if (!Uuid::isValid($addressId)) {
            throw UuidException::invalidUuid($addressId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $customer)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw CustomerException::addressNotFound($addressId);
        }

        return $address;
    }
}
