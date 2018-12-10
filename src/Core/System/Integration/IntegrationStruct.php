<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class IntegrationStruct extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var string
     */
    protected $secretAccessKey;

    /**
     * @var bool
     */
    protected $writeAccess;

    /**
     * @var \DateTime|null
     */
    protected $lastUsageAt;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function setSecretAccessKey(string $secretAccessKey): void
    {
        $this->secretAccessKey = $secretAccessKey;
    }

    public function getWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    public function setWriteAccess(bool $writeAccess): void
    {
        $this->writeAccess = $writeAccess;
    }

    public function getLastUsageAt(): ?\DateTime
    {
        return $this->lastUsageAt;
    }

    public function setLastUsageAt(\DateTime $lastUsageAt): void
    {
        $this->lastUsageAt = $lastUsageAt;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
