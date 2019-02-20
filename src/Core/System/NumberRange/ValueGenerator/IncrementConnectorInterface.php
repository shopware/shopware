<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\System\NumberRange\NumberRangeEntity;

interface IncrementConnectorInterface
{
    /**
     * fetch last used increment and reserves next
     */
    public function pullState(NumberRangeEntity $configuration, int $incrementBy = 1): string;
}
