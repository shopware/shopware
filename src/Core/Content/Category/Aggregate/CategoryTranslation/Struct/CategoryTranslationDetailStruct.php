<?php declare(strict_types=1);

namespace Shopware\Content\Category\Aggregate\CategoryTranslation\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;
use Shopware\Content\Category\Struct\CategoryBasicStruct;

class CategoryTranslationDetailStruct extends CategoryTranslationBasicStruct
{
    /**
     * @var CategoryBasicStruct
     */
    protected $category;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
