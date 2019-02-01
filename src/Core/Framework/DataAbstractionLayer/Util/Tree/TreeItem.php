<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

class TreeItem
{
    /**
     * @var TreeAwareInterface
     */
    protected $entity;

    /**
     * @var TreeItem[]
     */
    protected $children;

    public function __construct(TreeAwareInterface $entity, array $children)
    {
        $this->entity = $entity;
        $this->children = $children;
    }

    public function getEntity(): TreeAwareInterface
    {
        return $this->entity;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChildren(TreeItem ...$items): void
    {
        $this->children = array_merge($this->children, $items);
    }
}
