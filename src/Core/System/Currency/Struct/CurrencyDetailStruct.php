<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Struct;

use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;

class CurrencyDetailStruct extends CurrencyBasicStruct
{
    /**
     * @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection
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
