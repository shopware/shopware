<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class UnitTranslationCollection extends EntityCollection
{
    /**
     * @var UnitTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? UnitTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): UnitTranslationEntity
    {
        return parent::current();
    }

    public function getUnitIds(): array
    {
        return $this->fmap(function (UnitTranslationEntity $unitTranslation) {
            return $unitTranslation->getUnitId();
        });
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(function (UnitTranslationEntity $unitTranslation) use ($id) {
            return $unitTranslation->getUnitId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (UnitTranslationEntity $unitTranslation) {
            return $unitTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (UnitTranslationEntity $unitTranslation) use ($id) {
            return $unitTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return UnitTranslationEntity::class;
    }
}
