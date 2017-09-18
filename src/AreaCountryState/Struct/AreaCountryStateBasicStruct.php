<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Struct;

use Shopware\Framework\Struct\Struct;

class AreaCountryStateBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $areaCountryUuid;

    /**
     * @var string
     */
    protected $shortCode;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     */
    protected $active;

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

    public function getAreaCountryUuid(): string
    {
        return $this->areaCountryUuid;
    }

    public function setAreaCountryUuid(string $areaCountryUuid): void
    {
        $this->areaCountryUuid = $areaCountryUuid;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
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
