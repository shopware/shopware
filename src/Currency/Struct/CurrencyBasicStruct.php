<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Framework\Struct\Struct;

class CurrencyBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var bool
     */
    protected $isDefault;

    /**
     * @var float
     */
    protected $factor;

    /**
     * @var string
     */
    protected $symbol;

    /**
     * @var int
     */
    protected $symbolPosition;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string
     */
    protected $shortName;

    /**
     * @var string
     */
    protected $name;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getFactor(): float
    {
        return $this->factor;
    }

    public function setFactor(float $factor): void
    {
        $this->factor = $factor;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getSymbolPosition(): int
    {
        return $this->symbolPosition;
    }

    public function setSymbolPosition(int $symbolPosition): void
    {
        $this->symbolPosition = $symbolPosition;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
