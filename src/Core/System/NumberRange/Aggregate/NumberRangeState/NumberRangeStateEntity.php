<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class NumberRangeStateEntity extends Entity
{
    /**
     * @var string
     */
    protected $numberRangeId;

    /**
     * @var int
     */
    protected $lastValue;

    /**
     * @return string
     */
    public function getNumberRangeId(): string
    {
        return $this->numberRangeId;
    }

    /**
     * @param string $numberRangeId
     */
    public function setNumberRangeId(string $numberRangeId): void
    {
        $this->numberRangeId = $numberRangeId;
    }

    /**
     * @return int
     */
    public function getLastValue(): int
    {
        return $this->lastValue;
    }

    /**
     * @param int $lastValue
     */
    public function setLastValue(int $lastValue): void
    {
        $this->lastValue = $lastValue;
    }
}
