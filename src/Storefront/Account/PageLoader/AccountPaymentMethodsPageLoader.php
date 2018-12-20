<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Account\Page\AccountPaymentMethodsPageStruct;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\PageLoader\CurrencyPageletLoader;
use Shopware\Storefront\Content\PageLoader\LanguagePageletLoader;
use Shopware\Storefront\Content\PageLoader\ShopmenuPageletLoader;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Shopware\Storefront\Listing\PageLoader\NavigationPageletLoader;

class AccountPaymentMethodsPageLoader implements PageLoader
{
    /**
     * @var AccountPaymentMethodsPageletLoader
     */
    private $accountPaymentMethodsPageletLoader;

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
        AccountPaymentMethodsPageletLoader $accountPaymentMethodsPageletLoader,
        NavigationPageletLoader $navigationPageletLoader,
        CartInfoPageletLoader $cartInfoPageletLoader,
        ShopmenuPageletLoader $shopmenuPageletLoader,
        LanguagePageletLoader $languagePageletLoader,
        CurrencyPageletLoader $currencyPageletLoader
    ) {
        $this->accountPaymentMethodsPageletLoader = $accountPaymentMethodsPageletLoader;
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
     * @return AccountPaymentMethodsPageStruct
     */
    public function load(PageRequest $request, CheckoutContext $context): AccountPaymentMethodsPageStruct
    {
        $page = new AccountPaymentMethodsPageStruct();

        $page->attach(
            $this->accountPaymentMethodsPageletLoader->load($request, $context)
        );

        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param PageRequest                     $request
     * @param CheckoutContext                 $context
     * @param AccountPaymentMethodsPageStruct $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return AccountPaymentMethodsPageStruct
     */
    private function loadFrame(PageRequest $request, CheckoutContext $context, AccountPaymentMethodsPageStruct $page): AccountPaymentMethodsPageStruct
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
