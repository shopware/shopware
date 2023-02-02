<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
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
     * @var string
     */
    protected $action;

    /**
     * @var array|null
     */
    protected $payload;

    /**
     * @var VersionCommitEntity|null
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

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getVersionCommitId(): string
    {
        return $this->versionCommitId;
    }

    public function setVersionCommitId(string $versionCommitId): void
    {
        $this->versionCommitId = $versionCommitId;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

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

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getCommit(): ?VersionCommitEntity
    {
        return $this->commit;
    }

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
