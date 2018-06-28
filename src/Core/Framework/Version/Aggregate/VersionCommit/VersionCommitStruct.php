<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataCollection;
use Shopware\Core\Framework\Version\VersionStruct;

class VersionCommitStruct extends Entity
{
    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var string|null
     */
    protected $message;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var VersionCommitDataCollection
     */
    protected $data;

    /**
     * @var bool
     */
    protected $isMerge;

    /**
     * @var VersionStruct|null
     */
    protected $version;

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
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(?string $message): void
    {
        $this->message = $message;
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
     * @return null|string
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * @param null|string $userId
     */
    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return string
     */
    public function getVersionId(): string
    {
        return $this->versionId;
    }

    /**
     * @param string $versionId
     */
    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }

    public function getData(): VersionCommitDataCollection
    {
        return $this->data;
    }

    public function setData(VersionCommitDataCollection $data): void
    {
        $this->data = $data;
    }

    public function getIsMerge(): bool
    {
        return $this->isMerge;
    }

    public function setIsMerge(bool $isMerge): void
    {
        $this->isMerge = $isMerge;
    }

    public function getVersion(): ?VersionStruct
    {
        return $this->version;
    }

    public function setVersion(VersionStruct $version): void
    {
        $this->version = $version;
    }
}
