<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CmsBlockEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var CmsSlotCollection|null
     */
    protected $slots;

    /**
     * @var string
     */
    protected $pageId;

    /**
     * @var CmsPageEntity|null
     */
    protected $page;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getSlots(): ?CmsSlotCollection
    {
        return $this->slots;
    }

    public function setSlots(CmsSlotCollection $slots): void
    {
        $this->slots = $slots;
    }

    public function getPageId(): string
    {
        return $this->pageId;
    }

    public function setPageId(string $pageId): void
    {
        $this->pageId = $pageId;
    }

    public function getPage(): ?CmsPageEntity
    {
        return $this->page;
    }

    public function setPage(CmsPageEntity $page): void
    {
        $this->page = $page;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): \DateTime
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

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
