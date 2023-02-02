<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSection;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class CmsSectionEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var CmsBlockCollection|null
     */
    protected $blocks;

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
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $sizingMode;

    /**
     * @var string|null
     */
    protected $mobileBehavior;

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

    /**
     * @var string|null
     */
    protected $cmsPageVersionId;

    /**
     * @var array<string, bool>|null
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

    public function getBlocks(): ?CmsBlockCollection
    {
        return $this->blocks;
    }

    public function setBlocks(CmsBlockCollection $blocks): void
    {
        $this->blocks = $blocks;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSizingMode(): ?string
    {
        return $this->sizingMode;
    }

    public function setSizingMode(string $sizingMode): void
    {
        $this->sizingMode = $sizingMode;
    }

    public function getMobileBehavior(): ?string
    {
        return $this->mobileBehavior;
    }

    public function setMobileBehavior(?string $mobileBehavior): void
    {
        $this->mobileBehavior = $mobileBehavior;
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

    public function getCmsPageVersionId(): ?string
    {
        return $this->cmsPageVersionId;
    }

    public function setCmsPageVersionId(?string $cmsPageVersionId): void
    {
        $this->cmsPageVersionId = $cmsPageVersionId;
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
