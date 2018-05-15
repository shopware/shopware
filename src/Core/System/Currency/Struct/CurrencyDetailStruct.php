<?php declare(strict_types=1);

namespace Shopware\System\Currency\Struct;

use Shopware\System\Currency\Collection\CurrencyTranslationBasicCollection;

class CurrencyDetailStruct extends CurrencyBasicStruct
{
    /**
     * @var CurrencyTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new CurrencyTranslationBasicCollection();
    }

    public function getTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CurrencyTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
