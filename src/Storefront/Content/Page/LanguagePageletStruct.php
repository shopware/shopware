<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\Page;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Storefront\Framework\Page\PageletStruct;

class LanguagePageletStruct extends PageletStruct
{
    /**
     * @var EntitySearchResult
     */
    protected $languages;

    /**
     * @var LanguageEntity
     */
    protected $language;

    /**
     * @return EntitySearchResult
     */
    public function getLanguages(): EntitySearchResult
    {
        return $this->languages;
    }

    /**
     * @param EntitySearchResult $languages
     */
    public function setLanguages(EntitySearchResult $languages): void
    {
        $this->languages = $languages;
    }

    /**
     * @return LanguageEntity
     */
    public function getLanguage(): LanguageEntity
    {
        return $this->language;
    }

    /**
     * @param LanguageEntity $language
     */
    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
