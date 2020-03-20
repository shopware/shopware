<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(MediaTranslationEntity $entity)
 * @method void                        set(string $key, MediaTranslationEntity $entity)
 * @method MediaTranslationEntity[]    getIterator()
 * @method MediaTranslationEntity[]    getElements()
 * @method MediaTranslationEntity|null get(string $key)
 * @method MediaTranslationEntity|null first()
 * @method MediaTranslationEntity|null last()
 */
class MediaTranslationCollection extends EntityCollection
{
    public function getMediaIds(): array
    {
        return $this->fmap(function (MediaTranslationEntity $mediaTranslation) {
            return $mediaTranslation->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (MediaTranslationEntity $mediaTranslation) use ($id) {
            return $mediaTranslation->getMediaId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MediaTranslationEntity $mediaTranslation) {
            return $mediaTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MediaTranslationEntity $mediaTranslation) use ($id) {
            return $mediaTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'media_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaTranslationEntity::class;
    }
}
