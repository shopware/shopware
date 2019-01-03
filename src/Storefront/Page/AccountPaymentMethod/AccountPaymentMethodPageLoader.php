<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletLoader;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class AccountPaymentMethodPageLoader
{
    /**
     * @var AccountPaymentMethodPageletLoader
     */
    private $accountPaymentMethodPageletLoader;

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
        AccountPaymentMethodPageletLoader $accountPaymentMethodPageletLoader,
        NavigationPageletLoader $navigationPageletLoader,
        CartInfoPageletLoader $cartInfoPageletLoader,
        ShopmenuPageletLoader $shopmenuPageletLoader,
        LanguagePageletLoader $languagePageletLoader,
        CurrencyPageletLoader $currencyPageletLoader
    ) {
        $this->accountPaymentMethodPageletLoader = $accountPaymentMethodPageletLoader;
        $this->navigationPageletLoader = $navigationPageletLoader;
        $this->cartInfoPageletLoader = $cartInfoPageletLoader;
        $this->shopmenuPageletLoader = $shopmenuPageletLoader;
        $this->languagePageletLoader = $languagePageletLoader;
        $this->currencyPageletLoader = $currencyPageletLoader;
    }

    /**
     * @param AccountPaymentMethodPageRequest $request
     * @param CheckoutContext                 $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountPaymentMethodPageStruct
     */
    public function load(AccountPaymentMethodPageRequest $request, CheckoutContext $context): AccountPaymentMethodPageStruct
    {
        $page = new AccountPaymentMethodPageStruct();

        $page->setPaymentMethod(
            $this->accountPaymentMethodPageletLoader->load($request->getAccountPaymentMethodRequest(), $context)
        );

        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param AccountPaymentMethodPageRequest $request
     * @param CheckoutContext                 $context
     * @param AccountPaymentMethodPageStruct  $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountPaymentMethodPageStruct
     */
    private function loadFrame(AccountPaymentMethodPageRequest $request, CheckoutContext $context, AccountPaymentMethodPageStruct $page): AccountPaymentMethodPageStruct
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
