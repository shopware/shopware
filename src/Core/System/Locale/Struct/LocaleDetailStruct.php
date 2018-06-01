<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Struct;

use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationBasicCollection;

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
