<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertyGroupHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.displayType'])) {
            $entity->displayType = $row[$root . '.displayType'];
        }
        if (isset($row[$root . '.sortingType'])) {
            $entity->sortingType = $row[$root . '.sortingType'];
        }
        if (isset($row[$root . '.filterable'])) {
            $entity->filterable = (bool) $row[$root . '.filterable'];
        }
        if (isset($row[$root . '.visibleOnProductDetailPage'])) {
            $entity->visibleOnProductDetailPage = (bool) $row[$root . '.visibleOnProductDetailPage'];
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);

        return $entity;
    }
}
