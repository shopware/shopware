<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Unit\Struct\UnitTranslationBasicStruct;

class UnitTranslationBasicCollection extends EntityCollection
{
    /**
     * @var UnitTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? UnitTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): UnitTranslationBasicStruct
    {
        return parent::current();
    }

    public function getUnitUuids(): array
    {
        return $this->fmap(function (UnitTranslationBasicStruct $unitTranslation) {
            return $unitTranslation->getUnitUuid();
        });
    }

    public function filterByUnitUuid(string $uuid): UnitTranslationBasicCollection
    {
        return $this->filter(function (UnitTranslationBasicStruct $unitTranslation) use ($uuid) {
            return $unitTranslation->getUnitUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (UnitTranslationBasicStruct $unitTranslation) {
            return $unitTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): UnitTranslationBasicCollection
    {
        return $this->filter(function (UnitTranslationBasicStruct $unitTranslation) use ($uuid) {
            return $unitTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return UnitTranslationBasicStruct::class;
    }
}
