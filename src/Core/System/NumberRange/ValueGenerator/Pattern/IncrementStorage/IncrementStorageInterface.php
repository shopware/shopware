<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Shopware\Core\System\NumberRange\NumberRangeEntity;

interface IncrementStorageInterface
{
    /**
     * fetch last used increment and reserves next
     */
    public function pullState(NumberRangeEntity $configuration): string;

    /**
     * fetch next number without increment. Use to preview next Value
     */
    public function getNext(NumberRangeEntity $configuration): string;
}
