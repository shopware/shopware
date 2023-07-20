<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Contract\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Represents a media location
 *
 * Contains all information to generate the path for a media. Typically used in the media path strategy
 * and build over the database or by the request when the media was uploaded or renamed
 *
 * @final
 *
 * @public
 */
class MediaLocationStruct extends Struct
{
    public function __construct(
        public string $id,
        public ?string $extension,
        public ?string $fileName,
        public ?\DateTimeImmutable $uploadedAt
    ) {
    }

    public static function fromEntity(Entity $entity): self
    {
        return new self(
            $entity->getUniqueIdentifier(),
            $entity->get('fileExtension'),
            $entity->get('fileName'),
            $entity->get('uploadedAt')
        );
    }
}
