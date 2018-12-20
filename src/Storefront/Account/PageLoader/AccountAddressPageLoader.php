<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Account\Page\AccountAddressPageStruct;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\PageLoader\CurrencyPageletLoader;
use Shopware\Storefront\Content\PageLoader\LanguagePageletLoader;
use Shopware\Storefront\Content\PageLoader\ShopmenuPageletLoader;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Shopware\Storefront\Listing\PageLoader\NavigationPageletLoader;

class AccountAddressPageLoader implements PageLoader
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
     * @param PageRequest     $request
     * @param CheckoutContext $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountAddressPageStruct
     */
    public function load(PageRequest $request, CheckoutContext $context): AccountAddressPageStruct
    {
        $page = new AccountAddressPageStruct();

        $page->attach(
            $this->accountAddressPageletLoader->load($request, $context)
        );

        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param PageRequest              $request
     * @param CheckoutContext          $context
     * @param AccountAddressPageStruct $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountAddressPageStruct
     */
    private function loadFrame(PageRequest $request, CheckoutContext $context, AccountAddressPageStruct $page): AccountAddressPageStruct
    {
        $page->attach(
            $this->navigationPageletLoader->load($request, $context)
        );

        $page->attach(
            $this->cartInfoPageletLoader->load($request, $context)
        );

        $page->attach(
            $this->shopmenuPageletLoader->load($request, $context)
        );

        $page->attach(
            $this->languagePageletLoader->load($request, $context)
        );

        $page->attach(
            $this->currencyPageletLoader->load($request, $context)
        );

        return $page;
    }
}
