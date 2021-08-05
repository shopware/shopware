<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductCrossSellingHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.position'])) {
            $entity->position = (int) $row[$root . '.position'];
        }
        if (isset($row[$root . '.sortBy'])) {
            $entity->sortBy = $row[$root . '.sortBy'];
        }
        if (isset($row[$root . '.sortDirection'])) {
            $entity->sortDirection = $row[$root . '.sortDirection'];
        }
        if (isset($row[$root . '.type'])) {
            $entity->type = $row[$root . '.type'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (isset($row[$root . '.limit'])) {
            $entity->limit = (int) $row[$root . '.limit'];
        }
        if (isset($row[$root . '.productId'])) {
            $entity->productId = Uuid::fromBytesToHex($row[$root . '.productId']);
        }
        if (isset($row[$root . '.productStreamId'])) {
            $entity->productStreamId = Uuid::fromBytesToHex($row[$root . '.productStreamId']);
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->product = $this->manyToOne($row, $root, $definition->getField('product'), $context);
        $entity->productStream = $this->manyToOne($row, $root, $definition->getField('productStream'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
