<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class IntegrationEntity extends Entity
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
     * @var \DateTimeInterface|null
     */
    protected $lastUsageAt;

    /**
     * @var array|null
     */
    protected $customFields;

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

    public function getLastUsageAt(): ?\DateTimeInterface
    {
        return $this->lastUsageAt;
    }

    public function setLastUsageAt(\DateTimeInterface $lastUsageAt): void
    {
        $this->lastUsageAt = $lastUsageAt;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
