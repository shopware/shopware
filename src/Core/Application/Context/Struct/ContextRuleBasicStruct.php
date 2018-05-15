<?php declare(strict_types=1);

namespace Shopware\Application\Context\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\Context\Rule\Rule;

class ContextRuleBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Rule
     */
    protected $payload;

    /**
     * @var int
     */
    protected $priority;

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

    public function getPayload(): Rule
    {
        return $this->payload;
    }

    public function setPayload(Rule $payload): void
    {
        $this->payload = $payload;
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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }
}
