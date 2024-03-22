<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('buyers-experience')]
class MediaHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.userId'])) {
            $entity->userId = Uuid::fromBytesToHex($row[$root . '.userId']);
        }
        if (isset($row[$root . '.mediaFolderId'])) {
            $entity->mediaFolderId = Uuid::fromBytesToHex($row[$root . '.mediaFolderId']);
        }
        if (isset($row[$root . '.mimeType'])) {
            $entity->mimeType = $row[$root . '.mimeType'];
        }
        if (isset($row[$root . '.fileExtension'])) {
            $entity->fileExtension = $row[$root . '.fileExtension'];
        }
        if (isset($row[$root . '.uploadedAt'])) {
            $entity->uploadedAt = new \DateTimeImmutable($row[$root . '.uploadedAt']);
        }
        if (isset($row[$root . '.fileName'])) {
            $entity->fileName = $row[$root . '.fileName'];
        }
        if (isset($row[$root . '.fileSize'])) {
            $entity->fileSize = (int) $row[$root . '.fileSize'];
        }
        if (isset($row[$root . '.metaDataRaw'])) {
            $entity->metaDataRaw = $row[$root . '.metaDataRaw'];
        }
        if (isset($row[$root . '.mediaTypeRaw'])) {
            $entity->mediaTypeRaw = $row[$root . '.mediaTypeRaw'];
        }
        if (\array_key_exists($root . '.metaData', $row)) {
            $entity->metaData = $definition->decode('metaData', self::value($row, $root, 'metaData'));
        }
        if (\array_key_exists($root . '.config', $row)) {
            $entity->config = $definition->decode('config', self::value($row, $root, 'config'));
        }
        if (isset($row[$root . '.path'])) {
            $entity->path = $row[$root . '.path'];
        }
        if (isset($row[$root . '.private'])) {
            $entity->private = (bool) $row[$root . '.private'];
        }
        if (isset($row[$root . '.thumbnailsRo'])) {
            $entity->thumbnailsRo = $row[$root . '.thumbnailsRo'];
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }

        $entity->user = $this->manyToOne($row, $root, $definition->getField('user'), $context);
        $entity->mediaFolder = $this->manyToOne($row, $root, $definition->getField('mediaFolder'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);
        $this->manyToMany($row, $root, $entity, $definition->getField('tags'));

        return $entity;
    }
}
