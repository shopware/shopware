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
    protected $config;

    /**
     * @var array|null
     */
    protected $customFields;

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

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
