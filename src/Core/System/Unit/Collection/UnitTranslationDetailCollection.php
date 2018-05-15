<?php declare(strict_types=1);

namespace Shopware\System\Unit\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Unit\Struct\UnitTranslationDetailStruct;

class UnitTranslationDetailCollection extends UnitTranslationBasicCollection
{
    /**
     * @var UnitTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getUnits(): UnitBasicCollection
    {
        return new UnitBasicCollection(
            $this->fmap(function (UnitTranslationDetailStruct $unitTranslation) {
                return $unitTranslation->getUnit();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (UnitTranslationDetailStruct $unitTranslation) {
                return $unitTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return UnitTranslationDetailStruct::class;
    }
}
