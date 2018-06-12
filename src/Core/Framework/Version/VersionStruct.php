<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitCollection;

class VersionStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var string
     */
    protected $entityId;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var \Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitCollection
     */
    protected $commits;

    /**
     * VersionStruct constructor.
     */
    public function __construct()
    {
        $this->commits = new VersionCommitCollection();
    }

    /**
     * @return \Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitCollection
     */
    public function getCommits(): VersionCommitCollection
    {
        return $this->commits;
    }

    /**
     * @param VersionCommitCollection $commits
     */
    public function setCommits(VersionCommitCollection $commits): void
    {
        $this->commits = $commits;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }
}
