<?php declare(strict_types=1);

namespace Shopware\System\Unit\Aggregate\UnitTranslation\Struct;

use Shopware\System\Language\Struct\LanguageBasicStruct;
use Shopware\System\Unit\Struct\UnitBasicStruct;

class UnitTranslationDetailStruct extends UnitTranslationBasicStruct
{
    /**
     * @var UnitBasicStruct
     */
    protected $unit;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
