<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutRegisterPageLoader implements PageLoaderInterface
{
    /**
     * @var PageLoaderInterface
     */
    private $pageWithHeaderLoader;
    /**
     * @var AccountService
     */
    private $accountService;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AddressService
     */
    private $addressService;
    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        AccountService $accountService,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountService = $accountService;
        $this->eventDispatcher = $eventDispatcher;
        $this->addressService = $addressService;
        $this->cartService = $cartService;
    }

    public function load(Request $request, SalesChannelContext $context): CheckoutRegisterPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = CheckoutRegisterPage::createFrom($page);

        $page->setCountries(
            $this->addressService->getCountryList($context)
        );

        $page->setCart(
            $this->cartService->getCart($context->getToken(), $context)
        );

        $page->setSalutations($this->accountService->getSalutationList($context));

        $addressId = $request->attributes->get('addressId');
        if ($addressId) {
            $address = $this->addressService->getById((string) $addressId, $context);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            CheckoutRegisterPageLoadedEvent::NAME,
            new CheckoutRegisterPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
