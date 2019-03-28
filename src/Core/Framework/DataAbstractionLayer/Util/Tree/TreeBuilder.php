<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TreeBuilder
{
    /**
     * @throws \LogicException
     *
     * @return TreeItem[]
     */
    public static function buildTree(?string $parentId, EntityCollection $entities): array
    {
        $result = [];

        /** @var TreeAwareInterface $entity */
        foreach ($entities as $entity) {
            if (!$entity instanceof TreeAwareInterface) {
                // @todo this is clearly something different :)
                throw new \LogicException(sprintf('Expected instance of %s got %s', TreeAwareInterface::class, get_class($entity)));
            }

            if ($entity->getParentId() !== $parentId) {
                continue;
            }

            $result[] = new TreeItem(
                $entity,
                self::buildTree($entity->getId(), $entities)
            );
        }

        return $result;
    }
}
