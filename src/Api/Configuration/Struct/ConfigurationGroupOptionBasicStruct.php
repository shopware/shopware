<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Entity;

class ConfigurationGroupOptionBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $groupId;

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
     * @var ConfigurationGroupBasicStruct
     */
    protected $group;

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
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

    public function getGroup(): ConfigurationGroupBasicStruct
    {
        return $this->group;
    }

    public function setGroup(ConfigurationGroupBasicStruct $group): void
    {
        $this->group = $group;
    }
}
