<?php declare(strict_types=1);

namespace Shopware\Audit\Struct;

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
    protected $userUuid;

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

    public function getUserUuid(): string
    {
        return $this->userUuid;
    }

    public function setUserUuid(string $userUuid): void
    {
        $this->userUuid = $userUuid;
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
