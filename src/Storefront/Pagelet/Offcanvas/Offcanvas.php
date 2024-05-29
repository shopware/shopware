<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Offcanvas;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;

#[Package('core')]
class Offcanvas
{
    public function __construct(
        public CategoryCollection $categories,
        public CategoryEntity $category,
        public ?CurrencyCollection $currencies = null,
        public ?LanguageCollection $languages = null
    ) {
    }
}
