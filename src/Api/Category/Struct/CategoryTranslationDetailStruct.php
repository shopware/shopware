<?php declare(strict_types=1);

namespace Shopware\Api\Category\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CategoryTranslationDetailStruct extends CategoryTranslationBasicStruct
{
    /**
     * @var CategoryBasicStruct
     */
    protected $category;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getCategory(): CategoryBasicStruct
    {
        return $this->category;
    }

    public function setCategory(CategoryBasicStruct $category): void
    {
        $this->category = $category;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
