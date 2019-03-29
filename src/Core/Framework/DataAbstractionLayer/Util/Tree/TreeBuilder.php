<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TreeBuilder
{
    /**
     * @return TreeItem[]
     */
    public function buildTree(?string $parentId, EntityCollection $entities): array
    {
        return $entities->fmap(function (TreeAwareInterface $entity) use ($parentId, $entities): ?TreeItem {
            if ($entity->getParentId() !== $parentId) {
                return null;
            }

            return new TreeItem(
                $entity,
                $this->buildTree($entity->getId(), $entities)
            );
        });
    }
}
