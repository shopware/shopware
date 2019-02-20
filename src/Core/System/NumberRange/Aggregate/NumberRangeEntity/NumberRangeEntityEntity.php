<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeEntity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\NumberRange\NumberRangeCollection;

class NumberRangeEntityEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var bool
     */
    protected $global;

    /**
     * @var NumberRangeCollection|null
     */
    protected $numberRanges;

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function isGlobal(): bool
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
