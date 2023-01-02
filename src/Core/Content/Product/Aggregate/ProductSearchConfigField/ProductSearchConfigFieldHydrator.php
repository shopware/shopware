<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class ProductSearchConfigFieldHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.searchConfigId'])) {
            $entity->searchConfigId = Uuid::fromBytesToHex($row[$root . '.searchConfigId']);
        }
        if (isset($row[$root . '.customFieldId'])) {
            $entity->customFieldId = Uuid::fromBytesToHex($row[$root . '.customFieldId']);
        }
        if (isset($row[$root . '.field'])) {
            $entity->field = $row[$root . '.field'];
        }
        if (isset($row[$root . '.tokenize'])) {
            $entity->tokenize = (bool) $row[$root . '.tokenize'];
        }
        if (isset($row[$root . '.searchable'])) {
            $entity->searchable = (bool) $row[$root . '.searchable'];
        }
        if (isset($row[$root . '.ranking'])) {
            $entity->ranking = (int) $row[$root . '.ranking'];
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->searchConfig = $this->manyToOne($row, $root, $definition->getField('searchConfig'), $context);
        $entity->customField = $this->manyToOne($row, $root, $definition->getField('customField'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());

        return $entity;
    }
}
