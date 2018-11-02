<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaCollection extends EntityCollection
{
    /**
     * @var MediaStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaStruct
    {
        return parent::get($id);
    }

    public function current(): MediaStruct
    {
        return parent::current();
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (MediaStruct $media) {
            return $media->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (MediaStruct $media) use ($id) {
            return $media->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaStruct::class;
    }
}
