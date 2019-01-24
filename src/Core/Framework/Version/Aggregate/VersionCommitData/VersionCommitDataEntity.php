<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommitData;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitEntity;

class VersionCommitDataEntity extends Entity
{
    use EntityIdTrait;
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
     * @var VersionCommitEntity
     */
    protected $commit;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var string|null
     */
    protected $integrationId;

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
     * @return array|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array|null $payload
     */
    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return VersionCommitEntity
     */
    public function getCommit(): VersionCommitEntity
    {
        return $this->commit;
    }

    /**
     * @param VersionCommitEntity $commit
     */
    public function setCommit(VersionCommitEntity $commit): void
    {
        $this->commit = $commit;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }

    public function setIntegrationId(string $integrationId): void
    {
        $this->integrationId = $integrationId;
    }
}
