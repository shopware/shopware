<?php declare(strict_types=1);

namespace Shopware\Api\Context\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\Context\Rule\Rule;

class ContextCartModifierBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Rule|null
     */
    protected $rule;

    /**
     * @var float|null
     */
    protected $absolute;

    /**
     * @var float|null
     */
    protected $percental;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function setRule(?Rule $rule): void
    {
        $this->rule = $rule;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getAbsolute(): ?float
    {
        return $this->absolute;
    }

    public function setAbsolute(?float $absolute): void
    {
        $this->absolute = $absolute;
    }

    public function getPercental(): ?float
    {
        return $this->percental;
    }

    public function setPercental(?float $percental): void
    {
        $this->percental = $percental;
    }
}
