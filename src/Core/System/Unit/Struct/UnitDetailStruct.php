<?php declare(strict_types=1);

namespace Shopware\System\Unit\Struct;

use Shopware\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationBasicCollection;

class UnitDetailStruct extends UnitBasicStruct
{
    /**
     * @var \Shopware\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new UnitTranslationBasicCollection();
    }

    public function getTranslations(): UnitTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(UnitTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
