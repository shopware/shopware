<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Struct;

use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\Version\Collection\VersionCommitDataBasicCollection;

class VersionCommitBasicStruct extends Entity
{
    /**
     * @var int
     */
    protected $ai;

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
     * @var VersionCommitDataBasicCollection
     */
    protected $data;

    /**
     * VersionCommitBasicStruct constructor.
     */
    public function __construct()
    {
        $this->data = new VersionCommitDataBasicCollection();
    }

    /**
     * @return int
     */
    public function getAi(): int
    {
        return $this->ai;
    }

    /**
     * @param int $ai
     */
    public function setAi(int $ai): void
    {
        $this->ai = $ai;
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

    /**
     * @return VersionCommitDataBasicCollection
     */
    public function getData(): VersionCommitDataBasicCollection
    {
        return $this->data;
    }

    /**
     * @param VersionCommitDataBasicCollection $data
     */
    public function setData(VersionCommitDataBasicCollection $data): void
    {
        $this->data = $data;
    }
}
