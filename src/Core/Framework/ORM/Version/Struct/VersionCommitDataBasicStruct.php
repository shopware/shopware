<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Struct;

use Shopware\Core\Framework\ORM\Entity;

class VersionCommitDataBasicStruct extends Entity
{
    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var string
     */
    protected $versionCommitId;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var array
     */
    protected $entityId;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var array|null
     */
    protected $payload;

    /**
     * @var VersionCommitBasicStruct
     */
    protected $commit;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @return int
     */
    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    /**
     * @param int $autoIncrement
     */
    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * @return string
     */
    public function getVersionCommitId(): string
    {
        return $this->versionCommitId;
    }

    /**
     * @param string $versionCommitId
     */
    public function setVersionCommitId(string $versionCommitId): void
    {
        $this->versionCommitId = $versionCommitId;
    }

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @param string $entityName
     */
    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getEntityId(): array
    {
        return $this->entityId;
    }

    public function setEntityId(array $entityId): void
    {
        $this->entityId = $entityId;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * @return null|array
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param null|array $payload
     */
    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return VersionCommitBasicStruct
     */
    public function getCommit(): VersionCommitBasicStruct
    {
        return $this->commit;
    }

    /**
     * @param VersionCommitBasicStruct $commit
     */
    public function setCommit(VersionCommitBasicStruct $commit): void
    {
        $this->commit = $commit;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }
}
