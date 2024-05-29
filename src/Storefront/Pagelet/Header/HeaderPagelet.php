<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\NavigationPagelet;
use Shopware\Storefront\StorefrontException;

#[Package('storefront')]
class HeaderPagelet extends NavigationPagelet
{
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
     * @var CategoryCollection|null
     */
    protected $serviceMenu;

    /**
     * @internal
     */
    public function __construct(
        Tree|CategoryCollection $navigation,
        LanguageCollection $languages,
        CurrencyCollection $currencies,
        ?CategoryCollection $serviceMenu,
        // @deprecated tag:v6.7.0 - remove
        SalesChannelContext $context
    ) {
        $this->languages = $languages;
        $this->currencies = $currencies;
        $this->serviceMenu = $serviceMenu;

        $activeLanguage = $languages->get($context->getLanguageId());
        $activeCurrency = $currencies->get($context->getCurrencyId());

        if (!$activeCurrency) {
            throw StorefrontException::noActiveCurrency();
        }

        if (!$activeLanguage) {
            throw StorefrontException::noActiveLanguage();
        }

        $this->activeCurrency = $activeCurrency;
        $this->activeLanguage = $activeLanguage;

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

    public function getServiceMenu(): ?CategoryCollection
    {
        return $this->serviceMenu;
    }
}
