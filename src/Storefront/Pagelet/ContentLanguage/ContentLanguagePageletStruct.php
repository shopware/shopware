<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentLanguage;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Language\LanguageEntity;

class ContentLanguagePageletStruct extends Struct
{
    /**
     * @var EntitySearchResult
     */
    protected $languages;

    /**
     * @var LanguageEntity
     */
    protected $activeLanguage;

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
    public function getActiveLanguage(): LanguageEntity
    {
        return $this->activeLanguage;
    }

    /**
     * @param LanguageEntity $activeLanguage
     */
    public function setActiveLanguage(LanguageEntity $activeLanguage): void
    {
        $this->activeLanguage = $activeLanguage;
    }
}
