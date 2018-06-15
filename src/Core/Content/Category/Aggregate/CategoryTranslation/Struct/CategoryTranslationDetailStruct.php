<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Struct;

use Shopware\Core\Content\Category\Struct\CategoryBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

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
