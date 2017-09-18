<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Struct;

use Shopware\Framework\Struct\Struct;

class PriceGroupBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
