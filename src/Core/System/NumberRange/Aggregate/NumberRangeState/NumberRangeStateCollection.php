<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class NumberRangeStateCollection extends EntityCollection
{
    public function getNumberRangeIds(): array
    {
        return $this->fmap(function (NumberRangeStateEntity $numberRangeState) {
            return $numberRangeState->getNumberRangeId();
        });
    }

    public function filterByNumberRangeId(string $id): self
    {
        return $this->filter(function (NumberRangeStateEntity $numberRangeState) use ($id) {
            return $numberRangeState->getNumberRangeId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeStateEntity::class;
    }
}
