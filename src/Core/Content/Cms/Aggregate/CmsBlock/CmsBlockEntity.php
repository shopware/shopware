<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class CmsBlockEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $type;

    /**
     * @var CmsSlotCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $slots;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $sectionId;

    /**
     * @var CmsSectionEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $section;

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $position;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $sectionPosition;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $marginTop;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $marginBottom;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $marginLeft;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $marginRight;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $backgroundColor;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $backgroundMediaId;

    /**
     * @var MediaEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $backgroundMedia;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $backgroundMediaMode;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cssClass;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $locked;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cmsSectionVersionId;

    /**
     * @var array<string, bool>|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $visibility;

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

    public function getCmsSectionVersionId(): ?string
    {
        return $this->cmsSectionVersionId;
    }

    public function setCmsSectionVersionId(?string $cmsSectionVersionId): void
    {
        $this->cmsSectionVersionId = $cmsSectionVersionId;
    }

    /**
     * @return array<string, bool>|null
     */
    public function getVisibility(): ?array
    {
        return $this->visibility;
    }

    /**
     * @param array<string, bool>|null $visibility
     */
    public function setVisibility(?array $visibility): void
    {
        $this->visibility = $visibility;
    }
}
