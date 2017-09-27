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
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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
