<?php declare(strict_types=1);

namespace Shopware\Api\Category\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Collection\CategoryTranslationBasicCollection;

class CategoryDetailStruct extends CategoryBasicStruct
{
    /**
     * @var CategoryBasicStruct|null
     */
    protected $parent;

    /**
     * @var CategoryBasicCollection
     */
    protected $children;

    /**
     * @var CategoryTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->children = new CategoryBasicCollection();
        $this->translations = new CategoryTranslationBasicCollection();
    }

    public function getParent(): ?CategoryBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?CategoryBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): CategoryBasicCollection
    {
        return $this->children;
    }

    public function setChildren(CategoryBasicCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): CategoryTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CategoryTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
