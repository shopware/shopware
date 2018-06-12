<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class MediaTranslationCollection extends EntityCollection
{
    /**
     * @var MediaTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): MediaTranslationStruct
    {
        return parent::current();
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (MediaTranslationStruct $mediaTranslation) {
            return $mediaTranslation->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (MediaTranslationStruct $mediaTranslation) use ($id) {
            return $mediaTranslation->getMediaId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MediaTranslationStruct $mediaTranslation) {
            return $mediaTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MediaTranslationStruct $mediaTranslation) use ($id) {
            return $mediaTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaTranslationStruct::class;
    }
}
