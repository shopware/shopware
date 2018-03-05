<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Entity;



class ConfigurationGroupOptionBasicStruct extends Entity
{

    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string
     */
    protected $configurationGroupId;

    /**
     * @var string
     */
    protected $configurationGroupVersionId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $color;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var string|null
     */
    protected $mediaVersionId;


    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }


    public function getConfigurationGroupId(): string
    {
        return $this->configurationGroupId;
    }

    public function setConfigurationGroupId(string $configurationGroupId): void
    {
        $this->configurationGroupId = $configurationGroupId;
    }


    public function getConfigurationGroupVersionId(): string
    {
        return $this->configurationGroupVersionId;
    }

    public function setConfigurationGroupVersionId(string $configurationGroupVersionId): void
    {
        $this->configurationGroupVersionId = $configurationGroupVersionId;
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }


    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }


    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }


    public function getMediaVersionId(): ?string
    {
        return $this->mediaVersionId;
    }

    public function setMediaVersionId(?string $mediaVersionId): void
    {
        $this->mediaVersionId = $mediaVersionId;
    }

}