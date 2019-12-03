<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;

class HeaderPagelet extends Struct
{
    /**
     * @var Tree
     */
    protected $navigation;

    /**
     * @var LanguageCollection
     */
    protected $languages;

    /**
     * @var CurrencyCollection
     */
    protected $currencies;

    /**
     * @var LanguageEntity
     */
    protected $activeLanguage;

    /**
     * @var CurrencyEntity
     */
    protected $activeCurrency;

    /**
     * @var CategoryCollection
     */
    protected $serviceMenu;

    public function __construct(
        Tree $navigation,
        LanguageCollection $languages,
        CurrencyCollection $currencies,
        LanguageEntity $activeLanguage,
        CurrencyEntity $activeCurrency,
        CategoryCollection $serviceMenu
    ) {
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
