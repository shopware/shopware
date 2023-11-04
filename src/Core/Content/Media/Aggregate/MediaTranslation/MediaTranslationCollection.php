<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaTranslationEntity>
 */
#[Package('content')]
class MediaTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(fn (MediaTranslationEntity $mediaTranslation) => $mediaTranslation->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(fn (MediaTranslationEntity $mediaTranslation) => $mediaTranslation->getMediaId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (MediaTranslationEntity $mediaTranslation) => $mediaTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (MediaTranslationEntity $mediaTranslation) => $mediaTranslation->getLanguageId() === $id);
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
