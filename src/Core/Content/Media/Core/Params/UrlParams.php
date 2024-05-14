<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Params;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('buyers-experience')]
class UrlParams extends Struct
{
    public function __construct(
        public readonly string $id,
        public readonly UrlParamsSource $source,
        public readonly string $path,
        public readonly ?\DateTimeInterface $updatedAt = null
    ) {
    }

    public static function fromMedia(Entity $entity): self
    {
        return new self(
            id: $entity->getUniqueIdentifier(),
            source: UrlParamsSource::MEDIA,
            path: $entity->get('path'),
            updatedAt: $entity->get('updatedAt') ?? $entity->get('createdAt')
        );
    }

    public static function fromThumbnail(Entity $entity): self
    {
        return new self(
            id: $entity->getUniqueIdentifier(),
            source: UrlParamsSource::THUMBNAIL,
            path: $entity->get('path'),
            updatedAt: $entity->get('updatedAt') ?? $entity->get('createdAt')
        );
    }
}
