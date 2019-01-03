<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletLoader;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class AccountOrderPageLoader
{
    /**
     * @var AccountOrderPageletLoader
     */
    private $accountOrderPageletLoader;

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
        AccountOrderPageletLoader $accountOrderPageletLoader,
        NavigationPageletLoader $navigationPageletLoader,
        CartInfoPageletLoader $cartInfoPageletLoader,
        ShopmenuPageletLoader $shopmenuPageletLoader,
        LanguagePageletLoader $languagePageletLoader,
        CurrencyPageletLoader $currencyPageletLoader
    ) {
        $this->accountOrderPageletLoader = $accountOrderPageletLoader;
        $this->navigationPageletLoader = $navigationPageletLoader;
        $this->cartInfoPageletLoader = $cartInfoPageletLoader;
        $this->shopmenuPageletLoader = $shopmenuPageletLoader;
        $this->languagePageletLoader = $languagePageletLoader;
        $this->currencyPageletLoader = $currencyPageletLoader;
    }

    /**
     * @param AccountOrderPageRequest $request
     * @param CheckoutContext         $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException
     *
     * @return AccountOrderPageStruct
     */
    public function load(AccountOrderPageRequest $request, CheckoutContext $context): AccountOrderPageStruct
    {
        $page = new AccountOrderPageStruct();

        $page->setCustomerOrders(
            $this->accountOrderPageletLoader->load($request->getAccountOrderRequest(), $context)
        );

        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param AccountOrderPageRequest $request
     * @param CheckoutContext         $context
     * @param AccountOrderPageStruct  $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountOrderPageStruct
     */
    private function loadFrame(AccountOrderPageRequest $request, CheckoutContext $context, AccountOrderPageStruct $page): AccountOrderPageStruct
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
