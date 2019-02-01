<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Exception\InvalidParameterException;

class TreeBuilder
{
    /**
     * @param string|null      $parentId
     * @param EntityCollection $entities
     *
     * @throws InvalidParameterException
     *
     * @return TreeItem[]
     */
    public static function buildTree(?string $parentId, EntityCollection $entities): array
    {
        $result = [];

        /** @var TreeAwareInterface $entity */
        foreach ($entities as $entity) {
            if (!$entity instanceof TreeAwareInterface) {
                throw new InvalidParameterException(sprintf('Expected instance of %s got %s', TreeAwareInterface::class, get_class($entity)));
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
