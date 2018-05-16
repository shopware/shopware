<?php declare(strict_types=1);

namespace Shopware\System\Locale\Struct;

use Shopware\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationBasicCollection;

class LocaleDetailStruct extends LocaleBasicStruct
{
    /**
     * @var LocaleTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new LocaleTranslationBasicCollection();
    }

    public function getTranslations(): LocaleTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(LocaleTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
