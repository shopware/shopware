<?php declare(strict_types=1);

namespace Shopware\Schema\Struct;

use Shopware\Api\Entity\Entity;

class SchemaVersionBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $version;

    /**
     * @var \DateTime
     */
    protected $startDate;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var \DateTime|null
     */
    protected $completeDate;

    /**
     * @var string|null
     */
    protected $errorMsg;

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCompleteDate(): ?\DateTime
    {
        return $this->completeDate;
    }

    public function setCompleteDate(?\DateTime $completeDate): void
    {
        $this->completeDate = $completeDate;
    }

    public function getErrorMsg(): ?string
    {
        return $this->errorMsg;
    }

    public function setErrorMsg(?string $errorMsg): void
    {
        $this->errorMsg = $errorMsg;
    }
}
