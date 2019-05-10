<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\AddressBook\AddressBookPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddressBookPageletController extends StorefrontController
{
    /**
     * @var AddressBookPageletLoader|PageLoaderInterface
     */
    private $addressBookPageletLoader;

    /** @var AddressService */
    private $addressService;

    /** @var AccountService */
    private $accountService;

    /** @var TranslatorInterface */
    private $translator;

    /** @var SalesChannelContextServiceInterface */
    private $salesChannelContextService;

    public function __construct(
        PageLoaderInterface $addressBookPageletLoader,
        AddressService $addressService,
        AccountService $accountService,
        TranslatorInterface $translator,
        SalesChannelContextServiceInterface $salesChannelContextService
    ) {
        $this->addressBookPageletLoader = $addressBookPageletLoader;
        $this->addressService = $addressService;
        $this->accountService = $accountService;
        $this->translator = $translator;
        $this->salesChannelContextService = $salesChannelContextService;
    }

    /**
     * @Route(path="/widgets/account/address-book", name="widgets.account.addressbook", options={"seo"=true}, methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function addressBook(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $redirectRoute = $request->get('redirectRoute');
        $replaceSelector = $request->get('replaceSelector');

        $viewData = [];

        $viewData = $this->handleChangeableAddresses($viewData, $dataBag);
        $viewData = $this->handleAddressCreation($viewData, $dataBag, $context);
        $viewData = $this->handleAddressSelection($viewData, $dataBag, $context);

        $this->salesChannelContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        $viewData['page'] = $this->addressBookPageletLoader->load($request, $context);
        $viewData['redirectRoute'] = $redirectRoute;
        $viewData['replaceSelector'] = $replaceSelector;

        if ($request->get('redirectTo') || $request->get('forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->renderStorefront('@Storefront/component/address/address-editor-modal.html.twig', $viewData);
    }

    private function handleAddressCreation(array $viewData, RequestDataBag $dataBag, SalesChannelContext $context): array
    {
        /** @var DataBag|null $addressData */
        $addressData = $dataBag->get('address');
        $addressId = null;

        if (is_null($addressData)) {
            return $viewData;
        }

        try {
            $addressId = $dataBag->get('id');

            $this->addressService->create($addressData, $context);

            $success = true;
            $messages = ['type' => 'success', 'text' => $this->translator->trans('account.addressSaved')];
        } catch (\Exception $exception) {
            $success = false;
            $messages = ['type' => 'danger', 'text' => $this->translator->trans('error.message-default')];
        }

        $viewData['addressId'] = $addressId;
        $viewData['success'] = $success;
        $viewData['messages'] = $messages;

        return $viewData;
    }

    private function handleChangeableAddresses(array $viewData, RequestDataBag $dataBag): array
    {
        $changeableAddresses = $dataBag->get('changeableAddresses');

        if (is_null($changeableAddresses)) {
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
        $selecteAddress = $dataBag->get('selectAddress');

        if (is_null($selecteAddress)) {
            return $viewData;
        }

        $addressType = $selecteAddress->get('type');
        $addressId = $selecteAddress->get('id');

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

        $viewData['success'] = $success;

        return $viewData;
    }
}
