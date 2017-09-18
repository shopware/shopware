<?php declare(strict_types=1);

namespace Shopware\Holiday\Struct;

use Shopware\Framework\Struct\Struct;

class HolidayBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $calculation;

    /**
     * @var \DateTime
     */
    protected $eventDate;

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

    public function getCalculation(): string
    {
        return $this->calculation;
    }

    public function setCalculation(string $calculation): void
    {
        $this->calculation = $calculation;
    }

    public function getEventDate(): \DateTime
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTime $eventDate): void
    {
        $this->eventDate = $eventDate;
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
