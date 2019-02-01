<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

class Tree
{
    /**
     * @var TreeItem[]
     */
    protected $tree;

    /**
     * @var TreeAwareInterface
     */
    protected $active;

    public function __construct(TreeAwareInterface $active, array $tree)
    {
        $this->tree = $tree;
        $this->active = $active;
    }

    public function isSelected(TreeAwareInterface $navigation): bool
    {
        if ($navigation->getId() === $this->active->getId()) {
            return true;
        }

        $ids = explode('|', $this->active->getPath());

        return \in_array($navigation->getId(), $ids, true);
    }

    public function getTree(): array
    {
        return $this->tree;
    }

    public function setTree(array $tree): void
    {
        $this->tree = $tree;
    }

    public function getActive(): TreeAwareInterface
    {
        return $this->active;
    }

    public function setActive(TreeAwareInterface $active): void
    {
        $this->active = $active;
    }
}
