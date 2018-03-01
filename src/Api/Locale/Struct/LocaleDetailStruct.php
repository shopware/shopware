<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Struct;

use Shopware\Api\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\User\Collection\UserBasicCollection;

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
