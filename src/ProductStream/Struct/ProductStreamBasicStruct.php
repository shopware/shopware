<?php declare(strict_types=1);

namespace Shopware\ProductStream\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;

class ProductStreamBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $conditions;

    /**
     * @var int|null
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $listingSortingUuid;

    /**
     * @var ListingSortingBasicStruct
     */
    protected $sorting;

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

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): void
    {
        $this->type = $type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getListingSortingUuid(): ?string
    {
        return $this->listingSortingUuid;
    }

    public function setListingSortingUuid(?string $listingSortingUuid): void
    {
        $this->listingSortingUuid = $listingSortingUuid;
    }

    public function getSorting(): ListingSortingBasicStruct
    {
        return $this->sorting;
    }

    public function setSorting(ListingSortingBasicStruct $sorting): void
    {
        $this->sorting = $sorting;
    }
}
