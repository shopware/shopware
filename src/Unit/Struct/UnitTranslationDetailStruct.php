<?php declare(strict_types=1);

namespace Shopware\Unit\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class UnitTranslationDetailStruct extends UnitTranslationBasicStruct
{
    /**
     * @var UnitBasicStruct
     */
    protected $unit;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getUnit(): UnitBasicStruct
    {
        return $this->unit;
    }

    public function setUnit(UnitBasicStruct $unit): void
    {
        $this->unit = $unit;
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
