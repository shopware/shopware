<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreLicenseStruct extends Struct
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $technicalPluginName;

    /**
     * @var \DateTime
     */
    private $creationDate;

    /**
     * @var \DateTime
     */
    private $expirationDate;

    /**
     * @var StoreLicenseSubscriptionStruct|null
     */
    private $subscription;

    /**
     * @var StoreLicenseTypeStruct
     */
    private $type;

    /**
     * @var string
     */
    private $availableVersion;

    /**
     * @var bool
     */
    private $installed;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTechnicalPluginName(): string
    {
        return $this->technicalPluginName;
    }

    /**
     * @return $this
     */
    public function setTechnicalPluginName(string $technicalPluginName): self
    {
        $this->technicalPluginName = $technicalPluginName;

        return $this;
    }

    public function getCreationDate(): \DateTime
    {
        return $this->creationDate;
    }

    /**
     * @return $this
     */
    public function setCreationDate(\DateTime $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }

    /**
     * @return $this
     */
    public function setExpirationDate(\DateTime $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getSubscription(): ?StoreLicenseSubscriptionStruct
    {
        return $this->subscription;
    }

    /**
     * @return $this
     */
    public function setSubscription(?StoreLicenseSubscriptionStruct $subscription = null): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getType(): StoreLicenseTypeStruct
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    public function setType(StoreLicenseTypeStruct $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAvailableVersion(): string
    {
        return $this->availableVersion;
    }

    /**
     * @return $this
     */
    public function setAvailableVersion(string $availableVersion): self
    {
        $this->availableVersion = $availableVersion;

        return $this;
    }

    public function getInstalled(): bool
    {
        return $this->installed;
    }

    public function setInstalled(bool $installed): void
    {
        $this->installed = $installed;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'creationDate' => $this->getCreationDate()->format(DATE_ATOM),
            'expirationDate' => $this->getExpirationDate() !== null ? $this->getExpirationDate()->format(DATE_ATOM) : null,
            'technicalPluginName' => $this->getTechnicalPluginName(),
            'type' => $this->getType()->toArray(),
            'subscription' => $this->getSubscription() !== null ? $this->getSubscription()->toArray() : null,
            'installed' => $this->getInstalled(),
        ];
    }
}
