<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeType;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\NumberRange\NumberRangeCollection;

class NumberRangeTypeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $typeName;

    /**
     * @var bool
     */
    protected $global;

    /**
     * @var NumberRangeCollection|null
     */
    protected $numberRanges;

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function setTypeName(string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }

    public function getNumberRanges(): ?NumberRangeCollection
    {
        return $this->numberRanges;
    }

    public function setNumberRanges(?NumberRangeCollection $numberRanges): void
    {
        $this->numberRanges = $numberRanges;
    }
}
