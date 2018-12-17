<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaTranslationCollection extends EntityCollection
{
    /**
     * @var MediaTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): MediaTranslationEntity
    {
        return parent::current();
    }

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

    protected function getExpectedClass(): string
    {
        return MediaTranslationEntity::class;
    }
}
