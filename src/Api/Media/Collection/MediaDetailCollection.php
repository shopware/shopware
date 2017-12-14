<?php declare(strict_types=1);

namespace Shopware\Api\Media\Collection;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\Api\Media\Struct\MediaDetailStruct;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\User\Collection\UserBasicCollection;

class MediaDetailCollection extends MediaBasicCollection
{
    /**
     * @var MediaDetailStruct[]
     */
    protected $elements = [];

    public function getUsers(): UserBasicCollection
    {
        return new UserBasicCollection(
            $this->fmap(function (MediaDetailStruct $media) {
                return $media->getUser();
            })
        );
    }

    public function getCategoryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategories()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getElements());
        }

        return $collection;
    }

    public function getMailAttachmentUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMailAttachments()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMailAttachments(): MailAttachmentBasicCollection
    {
        $collection = new MailAttachmentBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMailAttachments()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): MediaTranslationBasicCollection
    {
        $collection = new MediaTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductMediaUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductMedia()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProductMedia(): ProductMediaBasicCollection
    {
        $collection = new ProductMediaBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductMedia()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return MediaDetailStruct::class;
    }
}
