<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation\NavigationTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NavigationEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string|null
     */
    protected $path;

    /**
     * @var int
     */
    protected $level;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var NavigationEntity|null
     */
    protected $parent;

    /**
     * @var NavigationCollection|null
     */
    protected $children;

    /**
     * @var NavigationTranslationCollection|null
     */
    protected $translations;

    /**
     * @var int
     */
    protected $childCount;

    /**
     * @var string|null
     */
    protected $categoryId;

    /**
     * @var CategoryEntity|null
     */
    protected $category;

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTime
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

    public function getParent(): ?NavigationEntity
    {
        return $this->parent;
    }

    public function setParent(NavigationEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getTranslations(): ?NavigationTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(NavigationTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getChildren(): ?NavigationCollection
    {
        return $this->children;
    }

    public function setChildren(NavigationCollection $children): void
    {
        $this->children = $children;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function compareWithCategory(): array
    {
        if (!$this->category) {
            return null;
        }
        $diff = [];

        if ($this->category->getName() !== $this->getName()) {
            $diff['name'] = $this->category->getName();
        }

        return $diff;
    }
}
