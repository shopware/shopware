<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountLogin\AccountLoginPageletLoader;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class AccountLoginPageLoader
{
    /**
     * @var AccountLoginPageletLoader
     */
    private $accountLoginPageletLoader;

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
        AccountLoginPageletLoader $accountLoginPageletLoader,
        NavigationPageletLoader $navigationPageletLoader,
        CartInfoPageletLoader $cartInfoPageletLoader,
        ShopmenuPageletLoader $shopmenuPageletLoader,
        LanguagePageletLoader $languagePageletLoader,
        CurrencyPageletLoader $currencyPageletLoader
    ) {
        $this->accountLoginPageletLoader = $accountLoginPageletLoader;
        $this->navigationPageletLoader = $navigationPageletLoader;
        $this->cartInfoPageletLoader = $cartInfoPageletLoader;
        $this->shopmenuPageletLoader = $shopmenuPageletLoader;
        $this->languagePageletLoader = $languagePageletLoader;
        $this->currencyPageletLoader = $currencyPageletLoader;
    }

    /**
     * @param LoginPageRequest $request
     * @param CheckoutContext  $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountLoginPageStruct
     */
    public function load(LoginPageRequest $request, CheckoutContext $context): AccountLoginPageStruct
    {
        $page = new AccountLoginPageStruct();
        $page->setLogin(
            $this->accountLoginPageletLoader->load($request->getLoginRequest(), $context)
        );
        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param LoginPageRequest       $request
     * @param CheckoutContext        $context
     * @param AccountLoginPageStruct $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountLoginPageStruct
     */
    private function loadFrame(LoginPageRequest $request, CheckoutContext $context, AccountLoginPageStruct $page): AccountLoginPageStruct
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
