<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

trait EnumerableFieldTrait
{
    /**
     * @var array <string|bool|int|float>
     */
    protected array $possibleValues = [];

    /**
     * @return array<string|bool|int|float>
     */
    public function getPossibleValues(): array
    {
        return $this->possibleValues;
    }

    /**
     * @param array<string|bool|int|float> $possibleValues
     */
    public function setPossibleValues(array $possibleValues): self
    {
        $this->possibleValues = $possibleValues;

        return $this;
    }
}
