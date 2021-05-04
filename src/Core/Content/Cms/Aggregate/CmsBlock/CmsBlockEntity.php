<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CmsBlockEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

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
    protected $sectionId;

    /**
     * @var CmsSectionEntity|null
     */
    protected $section;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $sectionPosition;

    /**
     * @var string|null
     */
    protected $marginTop;

    /**
     * @var string|null
     */
    protected $marginBottom;

    /**
     * @var string|null
     */
    protected $marginLeft;

    /**
     * @var string|null
     */
    protected $marginRight;

    /**
     * @var string|null
     */
    protected $backgroundColor;

    /**
     * @var string|null
     */
    protected $backgroundMediaId;

    /**
     * @var MediaEntity|null
     */
    protected $backgroundMedia;

    /**
     * @var string|null
     */
    protected $backgroundMediaMode;

    /**
     * @var string|null
     */
    protected $cssClass;

    /**
     * @var bool
     */
    protected $locked;

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

    public function getSectionId(): string
    {
        return $this->sectionId;
    }

    public function setSectionId(string $sectionId): void
    {
        $this->sectionId = $sectionId;
    }

    public function getSection(): ?CmsSectionEntity
    {
        return $this->section;
    }

    public function setSection(CmsSectionEntity $section): void
    {
        $this->section = $section;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSectionPosition(): ?string
    {
        return $this->sectionPosition;
    }

    public function setSectionPosition(?string $sectionPosition): void
    {
        $this->sectionPosition = $sectionPosition;
    }

    public function getMarginTop(): ?string
    {
        return $this->marginTop;
    }

    public function setMarginTop(string $marginTop): void
    {
        $this->marginTop = $marginTop;
    }

    public function getMarginBottom(): ?string
    {
        return $this->marginBottom;
    }

    public function setMarginBottom(string $marginBottom): void
    {
        $this->marginBottom = $marginBottom;
    }

    public function getMarginLeft(): ?string
    {
        return $this->marginLeft;
    }

    public function setMarginLeft(string $marginLeft): void
    {
        $this->marginLeft = $marginLeft;
    }

    public function getMarginRight(): ?string
    {
        return $this->marginRight;
    }

    public function setMarginRight(string $marginRight): void
    {
        $this->marginRight = $marginRight;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(string $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
    }

    public function getBackgroundMediaId(): ?string
    {
        return $this->backgroundMediaId;
    }

    public function setBackgroundMediaId(string $backgroundMediaId): void
    {
        $this->backgroundMediaId = $backgroundMediaId;
    }

    public function getBackgroundMedia(): ?MediaEntity
    {
        return $this->backgroundMedia;
    }

    public function setBackgroundMedia(MediaEntity $backgroundMedia): void
    {
        $this->backgroundMedia = $backgroundMedia;
    }

    public function getBackgroundMediaMode(): ?string
    {
        return $this->backgroundMediaMode;
    }

    public function setBackgroundMediaMode(string $backgroundMediaMode): void
    {
        $this->backgroundMediaMode = $backgroundMediaMode;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(string $cssClass): void
    {
        $this->cssClass = $cssClass;
    }

    public function getLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }
}
