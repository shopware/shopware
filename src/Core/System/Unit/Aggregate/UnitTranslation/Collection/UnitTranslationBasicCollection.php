<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct\UnitTranslationBasicStruct;

class UnitTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct\UnitTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? UnitTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): UnitTranslationBasicStruct
    {
        return parent::current();
    }

    public function getUnitIds(): array
    {
        return $this->fmap(function (UnitTranslationBasicStruct $unitTranslation) {
            return $unitTranslation->getUnitId();
        });
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(function (UnitTranslationBasicStruct $unitTranslation) use ($id) {
            return $unitTranslation->getUnitId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (UnitTranslationBasicStruct $unitTranslation) {
            return $unitTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (UnitTranslationBasicStruct $unitTranslation) use ($id) {
            return $unitTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return UnitTranslationBasicStruct::class;
    }
}
