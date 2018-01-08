<?php declare(strict_types=1);

namespace Shopware\Api\Media\Collection;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\Api\Media\Struct\MediaDetailStruct;
use Shopware\Api\Product\Collection\ProductManufacturerBasicCollection;
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

    public function getCategoryIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategories()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getElements());
        }

        return $collection;
    }

    public function getMailAttachmentIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMailAttachments()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getMailAttachments(): MailAttachmentBasicCollection
    {
        $collection = new MailAttachmentBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMailAttachments()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): MediaTranslationBasicCollection
    {
        $collection = new MediaTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductManufacturerIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductManufacturers()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getProductManufacturers(): ProductManufacturerBasicCollection
    {
        $collection = new ProductManufacturerBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductManufacturers()->getElements());
        }

        return $collection;
    }

    public function getProductMediaIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductMedia()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
