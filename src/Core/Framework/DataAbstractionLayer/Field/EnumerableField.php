<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

interface EnumerableField
{
    /**
     * @return array<string|bool|int|float>
     */
    public function getPossibleValues(): array;

    /**
     * @param array<string|bool|int|float> $possibleValues
     */
    public function setPossibleValues(array $possibleValues): self;
}
