<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Contract\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Represents a thumbnail location
 *
 * Contains all information to generate the path for a thumbnail. Typically used in the media path strategy
 * and build over the database or by the request when the media was uploaded or renamed
 *
 * @final
 *
 * @public
 */
class ThumbnailLocationStruct extends Struct
{
    public function __construct(
        public string $id,
        public int $width,
        public int $height,
        public MediaLocationStruct $media
    ) {
    }

    public static function fromEntity(Entity $entity, Entity $media): self
    {
        return new self(
            $entity->getUniqueIdentifier(),
            $entity->get('fileExtension'),
            $entity->get('fileName'),
            MediaLocationStruct::fromEntity($media)
        );
    }
}
