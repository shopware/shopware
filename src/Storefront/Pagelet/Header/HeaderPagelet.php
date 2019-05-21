<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Language\LanguageEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;

class HeaderPagelet extends Struct
{
    /**
     * @var Tree
     */
    private $navigation;

    /**
     * @var Tree
     */
    private $offcanvasNavigation;

    /**
     * @var LanguageCollection
     */
    private $languages;

    /**
     * @var CurrencyCollection
     */
    private $currencies;

    /**
     * @var LanguageEntity
     */
    private $activeLanguage;

    /**
     * @var CurrencyEntity
     */
    private $activeCurrency;

    /**
     * @var CategoryCollection
     */
    private $serviceMenu;

    public function __construct(
        Tree $navigation,
        Tree $offcanvasNavigation,
        LanguageCollection $languages,
        CurrencyCollection $currencies,
        LanguageEntity $activeLanguage,
        CurrencyEntity $activeCurrency,
        CategoryCollection $serviceMenu
    ) {
        $this->offcanvasNavigation = $offcanvasNavigation;
        $this->navigation = $navigation;
        $this->languages = $languages;
        $this->currencies = $currencies;
        $this->activeLanguage = $activeLanguage;
        $this->activeCurrency = $activeCurrency;
        $this->serviceMenu = $serviceMenu;
    }

    public function getNavigation(): Tree
    {
        return $this->navigation;
    }

    public function getOffcanvasNavigation(): Tree
    {
        return $this->offcanvasNavigation;
    }

    public function getLanguages(): LanguageCollection
    {
        return $this->languages;
    }

    public function getCurrencies(): CurrencyCollection
    {
        return $this->currencies;
    }

    public function getActiveLanguage(): LanguageEntity
    {
        return $this->activeLanguage;
    }

    public function getActiveCurrency(): CurrencyEntity
    {
        return $this->activeCurrency;
    }

    public function getServiceMenu(): CategoryCollection
    {
        return $this->serviceMenu;
    }
}
