<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TreeBuilder
{
    /**
     * @var TreeItem
     */
    private $item;

    public function __construct()
    {
        $this->item = new TreeItem(null, []);
    }

    /**
     * @return TreeItem[]
     */
    public function buildTree(?string $parentId, EntityCollection $entities): array
    {
        return $this->recursion($parentId, $entities->getElements());
    }

    private function recursion(?string $parentId, array $entities): array
    {
        $mapped = [];
        foreach ($entities as $key => $entity) {
            if ($entity->getParentId() !== $parentId) {
                continue;
            }

            unset($entities[$key]);

            $item = clone $this->item;
            $item->setEntity($entity);
            $item->setChildren(
                $this->recursion($entity->getId(), $entities)
            );

            $mapped[] = $item;
        }

        return $mapped;
    }
}
