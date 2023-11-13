<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Sorting;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class ProductSortingHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.locked'])) {
            $entity->locked = (bool) $row[$root . '.locked'];
        }
        if (isset($row[$root . '.key'])) {
            $entity->key = $row[$root . '.key'];
        }
        if (isset($row[$root . '.priority'])) {
            $entity->priority = (int) $row[$root . '.priority'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (\array_key_exists($root . '.fields', $row)) {
            $entity->fields = $definition->decode('fields', self::value($row, $root, 'fields'));
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
