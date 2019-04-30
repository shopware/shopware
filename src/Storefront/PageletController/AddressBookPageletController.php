<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddressBookPageletController extends StorefrontController
{
    /**
     * @var PageLoaderInterface
     */
    private $addressBookPageletLoader;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        PageLoaderInterface $addressBookPageletLoader,
        AddressService $addressService,
        TranslatorInterface $translator
    ) {
        $this->addressBookPageletLoader = $addressBookPageletLoader;
        $this->addressService = $addressService;
        $this->translator = $translator;
    }

    /**
     * @Route(path="/widgets/account/address-book", name="widgets.account.addressbook.get", options={"seo"=true}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function getAddresses(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->addressBookPageletLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/addressbook/modal.html.twig', ['page' => $page]);
    }

    /**
     * @Route(path="/widgets/account/address-book", name="widgets.account.addressbook.post", options={"seo"=true}, methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function postAddress(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        /** @var DataBag $addressData */
        $addressData = $dataBag->get('address');

        $addressId = $addressData->get('id');

        try {
            $this->addressService->create($addressData, $context);

            $success = true;
            $messages = ['type' => 'success', 'text' => $this->translator->trans('account.addressSaved')];
        } catch (\Exception $exception) {
            $success = false;
            $messages = ['type' => 'danger', 'text' => $this->translator->trans('error.message-default')];
        }

        $page = $this->addressBookPageletLoader->load($request, $context);

        return $this->render('@Storefront/page/addressbook/modal.html.twig', [
            'page' => $page,
            'addressId' => $addressId,
            'success' => $success,
            'messages' => $messages,
        ]);
    }
}
