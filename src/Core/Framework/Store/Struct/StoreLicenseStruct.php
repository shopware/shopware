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
     * @var StoreLicenseTypeStruct|null
     */
    private $type;

    /**
     * @var string
     */
    private $availableVersion;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getTechnicalPluginName(): string
    {
        return $this->technicalPluginName;
    }

    /**
     * @param string $technicalPluginName
     *
     * @return $this
     */
    public function setTechnicalPluginName(string $technicalPluginName): self
    {
        $this->technicalPluginName = $technicalPluginName;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate(): \DateTime
    {
        return $this->creationDate;
    }

    /**
     * @param \DateTime $creationDate
     *
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
    public function getExpirationDate(): \DateTime
    {
        return $this->expirationDate;
    }

    /**
     * @param \DateTime $expirationDate
     *
     * @return $this
     */
    public function setExpirationDate(\DateTime $expirationDate)
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    /**
     * @return StoreLicenseSubscriptionStruct|null
     */
    public function getSubscription(): ?StoreLicenseSubscriptionStruct
    {
        return $this->subscription;
    }

    /**
     * @param StoreLicenseSubscriptionStruct|null $subscription
     *
     * @return $this
     */
    public function setSubscription(StoreLicenseSubscriptionStruct $subscription = null): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    /**
     * @return StoreLicenseTypeStruct|null
     */
    public function getType(): ?StoreLicenseTypeStruct
    {
        return $this->type;
    }

    /**
     * @param StoreLicenseTypeStruct|null $type
     *
     * @return $this
     */
    public function setType(StoreLicenseTypeStruct $type = null): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getAvailableVersion(): string
    {
        return $this->availableVersion;
    }

    /**
     * @param string $availableVersion
     *
     * @return $this
     */
    public function setAvailableVersion(string $availableVersion): self
    {
        $this->availableVersion = $availableVersion;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'technicalPluginName' => $this->getTechnicalPluginName(),
            'type' => $this->getType()
        ];
    }
}
