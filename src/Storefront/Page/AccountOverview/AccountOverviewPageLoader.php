<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOverview;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletLoader;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class AccountOverviewPageLoader
{
    /**
     * @var AccountProfilePageletLoader
     */
    private $accountProfilePageletLoader;

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
        AccountProfilePageletLoader $accountProfilePageletLoader,
        NavigationPageletLoader $navigationPageletLoader,
        CartInfoPageletLoader $cartInfoPageletLoader,
        ShopmenuPageletLoader $shopmenuPageletLoader,
        LanguagePageletLoader $languagePageletLoader,
        CurrencyPageletLoader $currencyPageletLoader
    ) {
        $this->accountProfilePageletLoader = $accountProfilePageletLoader;
        $this->navigationPageletLoader = $navigationPageletLoader;
        $this->cartInfoPageletLoader = $cartInfoPageletLoader;
        $this->shopmenuPageletLoader = $shopmenuPageletLoader;
        $this->languagePageletLoader = $languagePageletLoader;
        $this->currencyPageletLoader = $currencyPageletLoader;
    }

    /**
     * @param AccountOverviewPageRequest $request
     * @param CheckoutContext            $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountOverviewPageStruct
     */
    public function load(AccountOverviewPageRequest $request, CheckoutContext $context): AccountOverviewPageStruct
    {
        $page = new AccountOverviewPageStruct();

        $page->setProfile(
            $this->accountProfilePageletLoader->load($request->getAccountProfileRequest(), $context)
        );

        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param AccountOverviewPageRequest $request
     * @param CheckoutContext            $context
     * @param AccountOverviewPageStruct  $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountOverviewPageStruct
     */
    private function loadFrame(AccountOverviewPageRequest $request, CheckoutContext $context, AccountOverviewPageStruct $page): AccountOverviewPageStruct
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
