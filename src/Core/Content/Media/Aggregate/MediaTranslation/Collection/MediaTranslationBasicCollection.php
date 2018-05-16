<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Content\Media\Aggregate\MediaTranslation\Struct\MediaTranslationBasicStruct;

class MediaTranslationBasicCollection extends EntityCollection
{
    /**
     * @var MediaTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MediaTranslationBasicStruct
    {
        return parent::current();
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (MediaTranslationBasicStruct $mediaTranslation) {
            return $mediaTranslation->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (MediaTranslationBasicStruct $mediaTranslation) use ($id) {
            return $mediaTranslation->getMediaId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MediaTranslationBasicStruct $mediaTranslation) {
            return $mediaTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MediaTranslationBasicStruct $mediaTranslation) use ($id) {
            return $mediaTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaTranslationBasicStruct::class;
    }
}
