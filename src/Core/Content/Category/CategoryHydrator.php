<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('content')]
class CategoryHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.versionId'])) {
            $entity->versionId = Uuid::fromBytesToHex($row[$root . '.versionId']);
        }
        if (isset($row[$root . '.parentId'])) {
            $entity->parentId = Uuid::fromBytesToHex($row[$root . '.parentId']);
        }
        if (isset($row[$root . '.afterCategoryId'])) {
            $entity->afterCategoryId = Uuid::fromBytesToHex($row[$root . '.afterCategoryId']);
        }
        if (isset($row[$root . '.mediaId'])) {
            $entity->mediaId = Uuid::fromBytesToHex($row[$root . '.mediaId']);
        }
        if (isset($row[$root . '.displayNestedProducts'])) {
            $entity->displayNestedProducts = (bool) $row[$root . '.displayNestedProducts'];
        }
        if (isset($row[$root . '.autoIncrement'])) {
            $entity->autoIncrement = (int) $row[$root . '.autoIncrement'];
        }
        if (isset($row[$root . '.level'])) {
            $entity->level = (int) $row[$root . '.level'];
        }
        if (\array_key_exists($root . '.path', $row)) {
            $entity->path = $definition->decode('path', self::value($row, $root, 'path'));
        }
        if (isset($row[$root . '.childCount'])) {
            $entity->childCount = (int) $row[$root . '.childCount'];
        }
        if (isset($row[$root . '.type'])) {
            $entity->type = $row[$root . '.type'];
        }
        if (isset($row[$root . '.productAssignmentType'])) {
            $entity->productAssignmentType = $row[$root . '.productAssignmentType'];
        }
        if (isset($row[$root . '.visible'])) {
            $entity->visible = (bool) $row[$root . '.visible'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (isset($row[$root . '.cmsPageId'])) {
            $entity->cmsPageId = Uuid::fromBytesToHex($row[$root . '.cmsPageId']);
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

        if (isset($row[$root . '.customEntityTypeId'])) {
            $entity->customEntityTypeId = Uuid::fromBytesToHex($row[$root . '.customEntityTypeId']);
        }

        $entity->media = $this->manyToOne($row, $root, $definition->getField('media'), $context);
        $entity->cmsPage = $this->manyToOne($row, $root, $definition->getField('cmsPage'), $context);
        $entity->productStream = $this->manyToOne($row, $root, $definition->getField('productStream'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);
        $this->manyToMany($row, $root, $entity, $definition->getField('products'));
        $this->manyToMany($row, $root, $entity, $definition->getField('nestedProducts'));
        $this->manyToMany($row, $root, $entity, $definition->getField('tags'));

        return $entity;
    }
}
