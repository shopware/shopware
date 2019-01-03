<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletLoader;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class AccountAddressPageLoader
{
    /**
     * @var AccountAddressPageletLoader
     */
    private $accountAddressPageletLoader;

    /**
     * @var NavigationPageletLoader
     */
    private $navigationPageletLoader;

    /**
     * @var CartInfoPageletLoader
     */
    private $cartInfoPageletLoader;

    /**
     * @var ShopmenuPageletLoader
     */
    private $shopmenuPageletLoader;

    /**
     * @var LanguagePageletLoader
     */
    private $languagePageletLoader;

    /**
     * @var CurrencyPageletLoader
     */
    private $currencyPageletLoader;

    public function __construct(
        AccountAddressPageletLoader $accountAddressPageletLoader,
        NavigationPageletLoader $navigationPageletLoader,
        CartInfoPageletLoader $cartInfoPageletLoader,
        ShopmenuPageletLoader $shopmenuPageletLoader,
        LanguagePageletLoader $languagePageletLoader,
        CurrencyPageletLoader $currencyPageletLoader
    ) {
        $this->accountAddressPageletLoader = $accountAddressPageletLoader;
        $this->navigationPageletLoader = $navigationPageletLoader;
        $this->cartInfoPageletLoader = $cartInfoPageletLoader;
        $this->shopmenuPageletLoader = $shopmenuPageletLoader;
        $this->languagePageletLoader = $languagePageletLoader;
        $this->currencyPageletLoader = $currencyPageletLoader;
    }

    /**
     * @param AccountAddressPageRequest $request
     * @param CheckoutContext           $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountAddressPageStruct
     */
    public function load(AccountAddressPageRequest $request, CheckoutContext $context): AccountAddressPageStruct
    {
        $page = new AccountAddressPageStruct();

        $page->setCustomerAdressPage(
            $this->accountAddressPageletLoader->load($request->getAddressRequest(), $context)
        );

        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param AccountAddressPageRequest $request
     * @param CheckoutContext           $context
     * @param AccountAddressPageStruct  $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountAddressPageStruct
     */
    private function loadFrame(AccountAddressPageRequest $request, CheckoutContext $context, AccountAddressPageStruct $page): AccountAddressPageStruct
    {
        $page->setNavigation(
            $this->navigationPageletLoader->load($request->getNavigationRequest(), $context)
        );

        $page->setCartInfo(
            $this->cartInfoPageletLoader->load($request->getCartInfoRequest(), $context)
        );

        $page->setShopmenu(
            $this->shopmenuPageletLoader->load($request->getShopmenuRequest(), $context)
        );

        $page->setLanguage(
            $this->languagePageletLoader->load($request->getLanguageRequest(), $context)
        );

        $page->setCurrency(
            $this->currencyPageletLoader->load($request->getCurrencyRequest(), $context)
        );

        return $page;
    }
}
