<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void             add(MediaEntity $entity)
 * @method void             set(string $key, MediaEntity $entity)
 * @method MediaEntity[]    getIterator()
 * @method MediaEntity[]    getElements()
 * @method MediaEntity|null get(string $key)
 * @method MediaEntity|null first()
 * @method MediaEntity|null last()
 */
class MediaCollection extends EntityCollection
{
    public function getUserIds(): array
    {
        return $this->fmap(function (MediaEntity $media) {
            return $media->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (MediaEntity $media) use ($id) {
            return $media->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaEntity::class;
    }
}
