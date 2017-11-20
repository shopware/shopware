<?php declare(strict_types=1);

namespace Shopware\Media\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Media\Struct\MediaTranslationBasicStruct;

class MediaTranslationBasicCollection extends EntityCollection
{
    /**
     * @var MediaTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? MediaTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): MediaTranslationBasicStruct
    {
        return parent::current();
    }

    public function getMediaUuids(): array
    {
        return $this->fmap(function (MediaTranslationBasicStruct $mediaTranslation) {
            return $mediaTranslation->getMediaUuid();
        });
    }

    public function filterByMediaUuid(string $uuid): MediaTranslationBasicCollection
    {
        return $this->filter(function (MediaTranslationBasicStruct $mediaTranslation) use ($uuid) {
            return $mediaTranslation->getMediaUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (MediaTranslationBasicStruct $mediaTranslation) {
            return $mediaTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): MediaTranslationBasicCollection
    {
        return $this->filter(function (MediaTranslationBasicStruct $mediaTranslation) use ($uuid) {
            return $mediaTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaTranslationBasicStruct::class;
    }
}
