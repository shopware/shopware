<?php declare(strict_types=1);

namespace Shopware\Area\Struct;

use Shopware\Framework\Struct\Struct;

class AreaBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

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
