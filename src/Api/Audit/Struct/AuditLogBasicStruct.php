<?php declare(strict_types=1);

namespace Shopware\Api\Audit\Struct;

use Shopware\Api\Entity\Entity;

class AuditLogBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var string|null
     */
    protected $foreignKey;

    /**
     * @var string|null
     */
    protected $payload;

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(?string $foreignKey): void
    {
        $this->foreignKey = $foreignKey;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function setPayload(?string $payload): void
    {
        $this->payload = $payload;
    }
}
