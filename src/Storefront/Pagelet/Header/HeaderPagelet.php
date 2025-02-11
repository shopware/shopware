<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Storefront\Pagelet\NavigationPagelet;

#[Package('framework')]
class HeaderPagelet extends NavigationPagelet
{
    /**
     * @internal
     */
    public function __construct(
        Tree $navigation,
        protected LanguageCollection $languages,
        protected CurrencyCollection $currencies,
        protected LanguageEntity $activeLanguage,
        protected CurrencyEntity $activeCurrency,
    ) {
        parent::__construct($navigation);
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
}
