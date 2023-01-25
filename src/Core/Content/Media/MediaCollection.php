<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaEntity>
 */
#[Package('content')]
class MediaCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(fn (MediaEntity $media) => $media->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(fn (MediaEntity $media) => $media->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'media_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaEntity::class;
    }
}
