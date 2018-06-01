<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct;

use Shopware\Core\System\Language\Struct\LanguageBasicStruct;
use Shopware\Core\System\Unit\Struct\UnitBasicStruct;

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
