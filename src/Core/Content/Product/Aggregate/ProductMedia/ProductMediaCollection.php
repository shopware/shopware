<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Media\MediaCollection;

use Shopware\Core\Framework\ORM\EntityCollection;

class ProductMediaCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductMediaStruct
    {
        return parent::get($id);
    }

    public function current(): ProductMediaStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductMediaStruct $productMedia) {
            return $productMedia->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductMediaStruct $productMedia) use ($id) {
            return $productMedia->getProductId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductMediaStruct $productMedia) {
            return $productMedia->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductMediaStruct $productMedia) use ($id) {
            return $productMedia->getMediaId() === $id;
        });
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(function (ProductMediaStruct $productMedia) {
                return $productMedia->getMedia();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductMediaStruct::class;
    }
}
